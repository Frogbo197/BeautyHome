<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class AdminMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $guard = Auth::guard('admin');

        if (! $guard->check()) {
            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bạn cần đăng nhập Admin.',
                ], 401);
            }

            return redirect()
                ->route('admin.login')
                ->with('warning', 'Vui lòng đăng nhập để vào trang quản trị.');
        }

        $admin = $guard->user();
        if (! $this->isActiveAdmin($admin)) {
            $guard->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            if ($request->expectsJson()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tài khoản Admin không hợp lệ hoặc đã bị khóa.',
                ], 403);
            }

            return redirect()
                ->route('admin.login')
                ->withErrors(['email' => 'Tài khoản Admin không hợp lệ hoặc đã bị khóa.']);
        }

        return $next($request);
    }

    private function isActiveAdmin(object $admin): bool
    {
        $role = (string) ($admin->VaiTroID ?? $admin->role ?? $admin->Role ?? '');
        $isAdminRole = in_array($role, ['2', 'admin', 'Admin', 'ADMIN'], true);
        $isActive = (int) ($admin->TrangThaiHoatDong ?? 1) === 1;

        return $isAdminRole && $isActive;
    }
}
