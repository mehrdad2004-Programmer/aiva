<?php

namespace Modules\Bot\Services\V1;

use Illuminate\Support\Facades\Http;

class BotService
{
    private string $baseUrl;

    private string $token;

    public function __construct()
    {
        $this->baseUrl = rtrim((string) env('BALE_BOT_BASE_URL'), '/');
        $this->token = (string) env('BALE_BOT_TOKEN');
    }

    public function sendMessage(string $chatId, string $message): array
    {
        try {
            $response = Http::post(
                $this->endpoint('sendMessage'),
                [
                    'chat_id' => $chatId,
                    'text' => $message,
                ]
            );

            return [
                'msg' => $response->json(),
                'statuscode' => $response->status(),
            ];
        } catch (\Exception $exception) {
            return [
                'msg' => [
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ],
                'statuscode' => 500,
            ];
        }
    }

    public function setWebhook(string $url): array
    {
        try {
            $response = Http::post(
                $this->endpoint('setWebhook'),
                ['url' => $url]
            );

            return [
                'msg' => $response->json(),
                'statuscode' => $response->status(),
            ];
        } catch (\Exception $exception) {
            return [
                'msg' => [
                    'ok' => false,
                    'message' => $exception->getMessage(),
                ],
                'statuscode' => 500,
            ];
        }
    }

    private function endpoint(string $method): string
    {
        return $this->baseUrl.$this->token.'/'.$method;
    }
}
