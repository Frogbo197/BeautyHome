<?php

namespace App\Service;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AuthTokenService
{
    public function issue(int $userId, string $email): string
    {
        $token = 'salud_'.Str::random(80);

        Cache::put($this->cacheKey($token), [
            'user_id' => $userId,
            'email' => $email,
            'issued_at' => now('Asia/Ho_Chi_Minh')->toDateTimeString(),
        ], now('Asia/Ho_Chi_Minh')->addDays(30));

        return $token;
    }

    public function userIdFromToken(?string $token): ?int
    {
        $token = trim((string) $token);
        if ($token === '') {
            return null;
        }

        $payload = Cache::get($this->cacheKey($token));

        return is_array($payload) && isset($payload['user_id'])
            ? (int) $payload['user_id']
            : null;
    }

    private function cacheKey(string $token): string
    {
        return 'api_token:'.hash('sha256', $token);
    }
}
