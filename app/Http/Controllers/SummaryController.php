<?php

namespace App\Http\Controllers;

use App\Service\DailySummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SummaryController extends Controller
{
    public function daily(Request $request, DailySummaryService $summaryService)
    {
        $validated = $request->validate([
            'user_id' => 'required|integer',
            'date' => 'nullable|date',
        ]);

        $date = $validated['date'] ?? now('Asia/Ho_Chi_Minh')->toDateString();
        $totals = $summaryService->refresh((int) $validated['user_id'], $date);

        return response()->json([
            'success' => true,
            'summary' => Schema::hasTable('tomtatsuckhoehangngay') ? DB::table('tomtatsuckhoehangngay')
                ->where('NguoiDungID', $validated['user_id'])
                ->where('Ngay', $date)
                ->first() : null,
            'totals' => $totals,
        ]);
    }
}
