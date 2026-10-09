<?php

namespace Modules\Task\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ValidateBaleMiniApp
{
    public function handle(Request $request, Closure $next): Response
    {
        $initData = $request->header('X-Bale-Init-Data');
        $botToken = config('services.bale.bot_token');

        if (! $initData || ! $botToken) {
            return $this->unauthorizedResponse();
        }

        parse_str($initData, $data);

        $hash = $data['hash'] ?? null;
        unset($data['hash']);

        if (! is_string($hash) || ! isset($data['auth_date'], $data['user'])) {
            return $this->unauthorizedResponse();
        }

        ksort($data);

        $dataCheckString = collect($data)
            ->map(fn ($value, $key) => $key.'='.$value)
            ->implode("\n");

        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        if (! hash_equals($calculatedHash, $hash)) {
            return $this->unauthorizedResponse();
        }

        $authDate = filter_var($data['auth_date'], FILTER_VALIDATE_INT);
        $maxAge = (int) config('services.bale.mini_app_max_age', 3600);

        if ($authDate === false || $authDate > now()->timestamp + 30 || now()->timestamp - $authDate > $maxAge) {
            return $this->unauthorizedResponse();
        }

        $user = json_decode($data['user'], true);

        if (! is_array($user) || ! isset($user['id'])) {
            return $this->unauthorizedResponse();
        }

        $request->attributes->set('bale_user_id', (string) $user['id']);

        return $next($request);
    }

    private function unauthorizedResponse(): JsonResponse
    {
        return response()->json([
            'msg' => 'invalid bale mini app data',
            'statuscode' => 401,
        ], 401);
    }
}
