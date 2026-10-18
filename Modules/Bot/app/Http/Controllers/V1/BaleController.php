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

    public function setWebhook(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'url' => ['required', 'url', 'starts_with:https://'],
        ]);

        $result = $this->botService->setWebhook($validated['url']);

        return response()->json($result['msg'], $result['statuscode']);
    }

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
                            $this->taskMessage($reminder->task, $reminder),
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
            ->where('status', '1')
            ->with('reminders')
            ->orderBy('id')
            ->get();

        if ($tasks->isEmpty()) {
            return $this->sendText($chatId, 'تسک انجام‌نشده‌ای ثبت نشده است.');
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
        $userId = (string) data_get($callbackQuery, 'from.id');
        $chatId = (string) (data_get($callbackQuery, 'message.chat.id') ?? $userId);
        $data = (string) data_get($callbackQuery, 'data');

        if (! str_starts_with($data, 'task_done:')) {
            return response()->json(['ok' => true]);
        }

        $taskId = substr($data, strlen('task_done:'));

        if (! ctype_digit($taskId)) {
            return response()->json(['ok' => true]);
        }

        $task = TasksModel::query()
            ->where('chat_id', $userId)
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

        $answerResult = $this->botService->answerCallbackQuery($callbackQueryId, $message);
        $messageResult = $this->botService->sendMessage($chatId, $message);

        if (! $this->wasSentSuccessfully($answerResult)) {
            return response()->json($answerResult['msg'], $answerResult['statuscode']);
        }

        return response()->json($messageResult['msg'], $messageResult['statuscode']);
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

    private function taskMessage(object $task, object $reminder): string
    {
        return implode("\n", [
            '⏰ یادآوری تسک',
            '━━━━━━━━━━━━',
            '📝 عنوان: '.$task->title,
            '🏷 نوع: '.($task->type ?: 'بدون نوع'),
            '🗓 زمان: '.$this->formatReminderDateTime($reminder->remind_at),
            '💬 توضیحات: '.($task->comments ?: 'بدون توضیحات'),
            '',
            'زمان انجام این تسک فرا رسیده است.',
        ]);
    }

    private function reportTaskMessage(object $task, int $number): string
    {
        $reminder = $task->reminders->first();
        $statuses = [
            '1' => '🟡 در صف',
            '2' => 'انجام شده ✅',
            '3' => 'حذف شده',
        ];

        return implode("\n", [
            '📌 تسک '.$this->toPersianDigits((string) $number),
            '━━━━━━━━━━━━',
            '📝 عنوان: '.$task->title,
            '🏷 نوع: '.$task->type,
            '📊 وضعیت: '.($statuses[$task->status] ?? $task->status),
            '🗓 زمان یادآوری: '.($reminder
                ? $this->formatReminderDateTime($reminder->remind_at)
                : 'بدون یادآوری'),
            '💬 توضیحات: '.($task->comments ?: 'بدون توضیحات'),
        ]);
    }

    private function formatReminderDateTime(object $dateTime): string
    {
        $localDateTime = $dateTime->copy()->timezone(
            (string) config('app.display_timezone', 'Asia/Tehran')
        );

        [$year, $month, $day] = $this->gregorianToJalali(
            (int) $localDateTime->format('Y'),
            (int) $localDateTime->format('n'),
            (int) $localDateTime->format('j')
        );

        return $this->toPersianDigits(sprintf(
            '%04d/%02d/%02d - %s',
            $year,
            $month,
            $day,
            $localDateTime->format('H:i')
        ));
    }

    private function gregorianToJalali(int $year, int $month, int $day): array
    {
        $monthDays = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];

        if ($year > 1600) {
            $jalaliYear = 979;
            $year -= 1600;
        } else {
            $jalaliYear = 0;
            $year -= 621;
        }

        $adjustedYear = $month > 2 ? $year + 1 : $year;
        $days = (365 * $year)
            + intdiv($adjustedYear + 3, 4)
            - intdiv($adjustedYear + 99, 100)
            + intdiv($adjustedYear + 399, 400)
            - 80
            + $day
            + $monthDays[$month - 1];

        $jalaliYear += 33 * intdiv($days, 12053);
        $days %= 12053;
        $jalaliYear += 4 * intdiv($days, 1461);
        $days %= 1461;

        if ($days > 365) {
            $jalaliYear += intdiv($days - 1, 365);
            $days = ($days - 1) % 365;
        }

        if ($days < 186) {
            $jalaliMonth = 1 + intdiv($days, 31);
            $jalaliDay = 1 + ($days % 31);
        } else {
            $jalaliMonth = 7 + intdiv($days - 186, 30);
            $jalaliDay = 1 + (($days - 186) % 30);
        }

        return [$jalaliYear, $jalaliMonth, $jalaliDay];
    }

    private function toPersianDigits(string $value): string
    {
        return strtr($value, [
            '0' => '۰',
            '1' => '۱',
            '2' => '۲',
            '3' => '۳',
            '4' => '۴',
            '5' => '۵',
            '6' => '۶',
            '7' => '۷',
            '8' => '۸',
            '9' => '۹',
        ]);
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
        $token = (string) config('services.bale.cron_token');

        return $token !== ''
            && hash_equals($token, (string) $request->bearerToken());
    }
}
