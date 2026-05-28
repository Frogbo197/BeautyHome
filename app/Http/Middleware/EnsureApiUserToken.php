<?php

namespace App\Http\Middleware;

use App\Service\AuthTokenService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureApiUserToken
{
    public function __construct(private readonly AuthTokenService $tokens)
    {
    }

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        $payload = $this->tokens->verify($token);

        if (!$payload) {
            return response()->json([
                'success' => false,
                'message' => 'Phiên đăng nhập không hợp lệ hoặc đã hết hạn.',
            ], 401);
        }

        $authUserId = (int) $payload['user_id'];
        $request->attributes->set('auth_user_id', $authUserId);

        if (!$this->requestBelongsToUser($request, $authUserId)) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền truy cập dữ liệu người dùng này.',
            ], 403);
        }

        return $next($request);
    }

    private function requestBelongsToUser(Request $request, int $authUserId): bool
    {
        foreach (['user_id', 'NguoiDungID', 'nguoi_dung_id'] as $key) {
            $value = $request->input($key) ?? $request->query($key);
            if ($value !== null && (int) $value !== $authUserId) {
                return false;
            }
        }

        foreach (['userId', 'nguoiDungId'] as $key) {
            $value = $request->route($key);
            if ($value !== null && (int) $value !== $authUserId) {
                return false;
            }
        }

        $uri = (string) optional($request->route())->uri();
        if ($request->isMethod('GET') && in_array($uri, ['home/{id}', 'water/{id}', 'water/{id}/stats'], true)) {
            return (int) $request->route('id') === $authUserId;
        }

        return true;
    }
}
