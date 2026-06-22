<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\Response;

class EnsureSystemNotInMaintenance
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Schema::hasTable('system_configs')) {
            $enabled = DB::table('system_configs')
                ->where('key', 'che_do_bao_tri')
                ->value('value');

            if (filter_var($enabled, FILTER_VALIDATE_BOOLEAN)) {
                return response()->json([
                    'success' => false,
                    'message' => 'Hệ thống đang bảo trì. Vui lòng quay lại sau.',
                ], 503);
            }
        }

        return $next($request);
    }
}
