<?php

namespace Modules\Bot\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Auth\Models\V1\UsersModel;
use Modules\Bot\Services\V1\BotService;
use Modules\Task\Models\V1\ReminderModel;
use Modules\Task\Models\V1\TasksModel;

class BaleController extends Controller
{
    public function __construct(private readonly BotService $botService) {}

    public function webhook(Request $request): JsonResponse
    {
        if ($request->has('callback_query')) {
            return $this->callbackQuery($request->input('callback_query'));
        }

        $message = $request->input('message');
        $senderId = data_get($message, 'from.id');
        $command = $this->commandFrom(
            data_get($message, 'text')
        );

        if ($senderId === null || $command === null) {
            return response()->json(['ok' => true]);
        }

        return match ($command) {
            '/start' => $this->start($message),
            '/report' => $this->report((string) $senderId),
            default => null,
        } ?? response()->json(['ok' => true]);
    }

    public function sendDueTasks(Request $request): JsonResponse
    {
        if (! $this->hasValidCronToken($request)) {
            return response()->json(['message' => 'Unauthorized'], 401);
        }

        $lock = Cache::lock('bale:dispatch-due-tasks', 300);

        if (! $lock->get()) {
            return response()->json([
                'message' => 'Due task dispatch is already running.',
            ], 409);
        }

        try {
            $sent = 0;
            $failed = 0;

            ReminderModel::query()
                ->whereNull('sent_at')
                ->where('remind_at', '<=', now())
                ->whereHas('task', fn ($query) => $query->where('status', '1'))
                ->with('task')
                ->orderBy('id')
                ->chunkById(100, function ($reminders) use (&$sent, &$failed): void {
                    foreach ($reminders as $reminder) {
                        $result = $this->botService->sendMessage(
                            (string) $reminder->chat_id,
                            $this->taskMessage($reminder->task),
                            $this->doneButton($reminder->task->id)
                        );

                        if ($this->wasSentSuccessfully($result)) {
                            $reminder->forceFill(['sent_at' => now()])->save();
                            $sent++;
                        } else {
                            $failed++;
                        }
                    }
                });

            return response()->json([
                'message' => 'Due task dispatch completed.',
                'sent' => $sent,
                'failed' => $failed,
            ]);
        } finally {
            $lock->release();
        }
    }

    private function start(array $message): JsonResponse
    {
        $chatId = (string) data_get($message, 'from.id');

        $user = UsersModel::withTrashed()->firstOrNew(['chat_id' => $chatId]);
        $user->fullname = $this->senderName($message);
        $user->deleted_at = null;
        $user->save();

        return $this->sendText(
            $chatId,
            'Welcome! Send /report to receive a report.'
        );
    }

    private function report(string $chatId): JsonResponse
    {
        $tasks = TasksModel::query()
            ->where('chat_id', $chatId)
            ->whereHas('reminders', fn ($query) => $query
                ->whereBetween('remind_at', [now()->startOfDay(), now()->endOfDay()]))
            ->with(['reminders' => fn ($query) => $query
                ->whereBetween('remind_at', [now()->startOfDay(), now()->endOfDay()])])
            ->orderBy('id')
            ->get();

        if ($tasks->isEmpty()) {
            return $this->sendText($chatId, 'برای امروز تسکی ثبت نشده است.');
        }

        $sent = 0;
        $failed = 0;

        foreach ($tasks as $index => $task) {
            $result = $this->botService->sendMessage(
                $chatId,
                $this->reportTaskMessage($task, $index + 1),
                $task->status === '1' ? $this->doneButton($task->id) : null
            );

            if ($this->wasSentSuccessfully($result)) {
                $sent++;
            } else {
                $failed++;
            }
        }

        return response()->json([
            'ok' => $failed === 0,
            'sent' => $sent,
            'failed' => $failed,
        ]);
    }

    private function callbackQuery(array $callbackQuery): JsonResponse
    {
        $callbackQueryId = (string) data_get($callbackQuery, 'id');
        $chatId = (string) data_get($callbackQuery, 'from.id');
        $data = (string) data_get($callbackQuery, 'data');

        if (! str_starts_with($data, 'task_done:')) {
            return response()->json(['ok' => true]);
        }

        $taskId = substr($data, strlen('task_done:'));

        if (! ctype_digit($taskId)) {
            return response()->json(['ok' => true]);
        }

        $task = TasksModel::query()
            ->where('chat_id', $chatId)
            ->find((int) $taskId);

        if (! $task) {
            $result = $this->botService->answerCallbackQuery(
                $callbackQueryId,
                'این تسک پیدا نشد.'
            );

            return response()->json($result['msg'], $result['statuscode']);
        }

        if ($task->status === '2') {
            $message = 'این تسک قبلاً انجام شده است. ✅';
        } else {
            $task->update(['status' => '2']);
            $message = 'تسک با موفقیت انجام شد. ✅';
        }

        $result = $this->botService->answerCallbackQuery($callbackQueryId, $message);

        return response()->json($result['msg'], $result['statuscode']);
    }

    private function commandFrom(mixed $text): ?string
    {
        if (! is_string($text)) {
            return null;
        }

        $command = explode(' ', trim($text), 2)[0];

        return explode('@', $command, 2)[0];
    }

    private function sendText(string $chatId, string $message): JsonResponse
    {
        $result = $this->botService->sendMessage($chatId, $message);

        return response()->json($result['msg'], $result['statuscode']);
    }

    private function senderName(array $message): string
    {
        $name = trim(implode(' ', array_filter([
            data_get($message, 'from.first_name'),
            data_get($message, 'from.last_name'),
        ])));

        return $name !== ''
            ? $name
            : (string) (data_get($message, 'from.username') ?? data_get($message, 'from.id'));
    }

    private function taskMessage(object $task): string
    {
        return implode("\n", array_filter([
            'یادآوری: '.$task->title,
            $task->comments,
        ]));
    }

    private function reportTaskMessage(object $task, int $number): string
    {
        $reminder = $task->reminders->first();
        $statuses = [
            '1' => 'در صف',
            '2' => 'انجام شده ✅',
            '3' => 'حذف شده',
        ];

        return implode("\n", array_filter([
            'تسک '.$number.': '.$task->title,
            'نوع: '.$task->type,
            'وضعیت: '.($statuses[$task->status] ?? $task->status),
            $reminder ? 'زمان یادآوری: '.$reminder->remind_at->format('H:i') : null,
            $task->comments,
        ]));
    }

    private function doneButton(int $taskId): array
    {
        return [
            'inline_keyboard' => [[
                [
                    'text' => '✅ انجام شد',
                    'callback_data' => 'task_done:'.$taskId,
                ],
            ]],
        ];
    }

    private function wasSentSuccessfully(array $result): bool
    {
        return $result['statuscode'] >= 200
            && $result['statuscode'] < 300
            && data_get($result, 'msg.ok') === true;
    }

    private function hasValidCronToken(Request $request): bool
    {
        $token = (string) env('BALE_CRON_TOKEN');

        return $token !== ''
            && hash_equals($token, (string) $request->bearerToken());
    }
}
