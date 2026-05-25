<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdminRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $configuredToken = (string) env('ADMIN_API_TOKEN', '');
        $providedToken = (string) ($request->bearerToken() ?: $request->header('X-Admin-Token', ''));

        if ($configuredToken !== '' && hash_equals($configuredToken, $providedToken)) {
            return $next($request);
        }

        $user = $request->user();
        if ($user && $this->isAdminId((int) ($user->ID ?? $user->id ?? 0))) {
            return $next($request);
        }

        $headerUserId = (int) $request->header('X-Admin-User-Id', 0);
        if ($configuredToken === '' && app()->environment(['local', 'testing']) && $this->isAdminId($headerUserId)) {
            return $next($request);
        }

        return response()->json([
            'success' => false,
            'message' => 'Admin permission required',
        ], 403);
    }

    private function isAdminId(int $userId): bool
    {
        if ($userId <= 0 || !Schema::hasTable('taikhoan')) {
            return false;
        }

        $role = DB::table('taikhoan')->where('ID', $userId)->value('VaiTroID');
        return in_array((string) $role, ['1', 'admin', 'Admin', 'ADMIN'], true);
    }
}
