<?php

namespace App\Service;

class AuthTokenService
{
    private const TTL_SECONDS = 60 * 60 * 24 * 30;

    public function issue(int $userId, string $email): string
    {
        $payload = [
            'user_id' => $userId,
            'email' => $email,
            'exp' => time() + self::TTL_SECONDS,
        ];

        $body = $this->base64UrlEncode(json_encode($payload, JSON_THROW_ON_ERROR));
        $signature = hash_hmac('sha256', $body, $this->secret());

        return $body . '.' . $signature;
    }

    public function verify(?string $token): ?array
    {
        if (!$token || !str_contains($token, '.')) {
            return null;
        }

        [$body, $signature] = explode('.', $token, 2);
        $expected = hash_hmac('sha256', $body, $this->secret());
        if (!hash_equals($expected, $signature)) {
            return null;
        }

        $decoded = json_decode($this->base64UrlDecode($body), true);
        if (!is_array($decoded) || empty($decoded['user_id']) || empty($decoded['exp'])) {
            return null;
        }

        if ((int) $decoded['exp'] < time()) {
            return null;
        }

        return $decoded;
    }

    private function secret(): string
    {
        return (string) config('app.key');
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $value): string
    {
        return base64_decode(strtr($value, '-_', '+/')) ?: '';
    }
}
