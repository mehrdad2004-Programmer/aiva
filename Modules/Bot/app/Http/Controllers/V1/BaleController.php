<?php

namespace Modules\Bot\Http\Controllers\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Modules\Auth\Models\V1\UsersModel;
use Modules\Bot\Services\V1\BotService;
use Modules\Task\Models\V1\ReminderModel;

class BaleController extends Controller
{
    public function __construct(private readonly BotService $botService) {}

    public function webhook(Request $request): JsonResponse
    {
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
                            $this->taskMessage($reminder->task)
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
        return $this->sendText(
            $chatId,
            'Your report request has been received.'
        );
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
