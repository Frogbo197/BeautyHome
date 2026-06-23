<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Service\DailySummaryService;
use App\Service\HealthRiskEngineService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ActivityController extends Controller
{
    public function index(Request $request)
    {
        if (!Schema::hasTable('activity_logs') && Schema::hasTable('lichhoatdong')) {
            $query = DB::table('lichhoatdong')
                ->leftJoin('hoatdong', 'hoatdong.ID', '=', 'lichhoatdong.HoatDongID')
                ->leftJoin('chitiethoatdong', 'chitiethoatdong.LichHoatDongID', '=', 'lichhoatdong.ID');

            if ($request->filled('user_id') || $request->filled('NguoiDungID')) {
                $query->where('lichhoatdong.NguoiDungID', $request->integer('user_id') ?: $request->integer('NguoiDungID'));
            }
            if ($request->filled('NgayHoatDong') || $request->filled('Ngay')) {
                $query->whereDate('lichhoatdong.ThoiGianBatDau', $request->input('NgayHoatDong') ?? $request->input('Ngay'));
            }

            $data = $query
                ->orderByDesc('lichhoatdong.ID')
                ->limit(100)
                ->get([
                    'lichhoatdong.ID',
                    'lichhoatdong.NguoiDungID',
                    'hoatdong.TenHoatDong',
                    DB::raw('TIMESTAMPDIFF(MINUTE, lichhoatdong.ThoiGianBatDau, lichhoatdong.ThoiGianKetThuc) as ThoiLuongPhut'),
                    DB::raw('COALESCE(chitiethoatdong.CaloDot, hoatdong.Calo, 0) as CaloriesDot'),
                    DB::raw('DATE(lichhoatdong.ThoiGianBatDau) as NgayHoatDong'),
                    DB::raw("'Phổ biến' as LoaiHoatDong"),
                    DB::raw("'Trung bình' as MucDo"),
                ]);

            return response()->json(['success' => true, 'data' => $data]);
        }

        $query = ActivityLog::query();

        if ($request->filled('user_id') || $request->filled('NguoiDungID')) {
            $query->where('NguoiDungID', $request->integer('user_id') ?: $request->integer('NguoiDungID'));
        }

        if ($request->filled('NgayHoatDong') || $request->filled('Ngay')) {
            $query->whereDate('NgayHoatDong', $request->input('NgayHoatDong') ?? $request->input('Ngay'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderByDesc('ID')->limit(100)->get(),
        ]);
    }

    public function stats(Request $request)
    {
        $userId = $request->integer('user_id') ?: $request->integer('NguoiDungID');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Thiếu user_id'], 422);
        }

        $range = $request->query('range', 'week');
        $now = now('Asia/Ho_Chi_Minh');

        if (!Schema::hasTable('activity_logs') && Schema::hasTable('lichhoatdong')) {
            $query = DB::table('lichhoatdong')
                ->leftJoin('hoatdong', 'hoatdong.ID', '=', 'lichhoatdong.HoatDongID')
                ->leftJoin('chitiethoatdong', 'chitiethoatdong.LichHoatDongID', '=', 'lichhoatdong.ID')
                ->where('lichhoatdong.NguoiDungID', $userId);

            $dateColumn = 'lichhoatdong.ThoiGianBatDau';
            $selectBase = "COALESCE(hoatdong.TenHoatDong, 'Hoạt động') as ten_bai_tap";
            $durationExpr = 'TIMESTAMPDIFF(MINUTE, lichhoatdong.ThoiGianBatDau, lichhoatdong.ThoiGianKetThuc)';
            $calorieExpr = 'COALESCE(chitiethoatdong.CaloDot, hoatdong.Calo, 0)';
        } else {
            $query = DB::table('activity_logs')->where('NguoiDungID', $userId);
            $dateColumn = 'NgayHoatDong';
            $selectBase = "COALESCE(TenHoatDong, 'Hoạt động') as ten_bai_tap";
            $durationExpr = 'COALESCE(ThoiLuongPhut, 0)';
            $calorieExpr = 'COALESCE(CaloriesDot, 0)';
        }

        if ($range === 'year') {
            $query->whereYear($dateColumn, $now->year);
            $label = "MONTH($dateColumn)";
        } elseif ($range === 'month') {
            $query->whereYear($dateColumn, $now->year)->whereMonth($dateColumn, $now->month);
            $label = "DATE($dateColumn)";
        } else {
            $query->whereDate($dateColumn, '>=', $now->copy()->startOfWeek()->toDateString())
                ->whereDate($dateColumn, '<=', $now->copy()->endOfWeek()->toDateString());
            $label = "DATE($dateColumn)";
        }

        $rows = $query
            ->selectRaw("$label as label, $selectBase, COUNT(*) as so_lan, SUM($durationExpr) as tong_phut, SUM($calorieExpr) as tong_kcal, MAX($calorieExpr) as max_kcal")
            ->groupByRaw("$label, ten_bai_tap")
            ->orderByRaw($label)
            ->get();

        if ((float) $rows->sum('tong_kcal') <= 0 && (float) $rows->sum('tong_phut') <= 0) {
            $summaryRows = $this->activityRowsFromDailySummary($userId, $range);
            if ($summaryRows->isNotEmpty()) {
                $rows = $summaryRows;
            }
        }

        $topDuration = $rows->sortByDesc('tong_phut')->first();
        $topCalories = $rows->sortByDesc('tong_kcal')->first();
        $topFrequent = $rows->sortByDesc('so_lan')->first();
        $topIntensity = $rows->sortByDesc('max_kcal')->first();

        return response()->json([
            'success' => true,
            'range' => $range,
            'summary' => [
                'so_bai_tap' => (int) $rows->sum('so_lan'),
                'tong_phut' => (int) $rows->sum('tong_phut'),
                'tong_gio' => round(((int) $rows->sum('tong_phut')) / 60, 1),
                'tong_kcal' => (int) $rows->sum('tong_kcal'),
            ],
            'highlights' => [
                'highest_duration' => $topDuration,
                'highest_intensity' => $topIntensity,
                'highest_calories' => $topCalories,
                'most_frequent' => $topFrequent,
            ],
            'data' => $rows,
        ]);
    }

    private function activityRowsFromDailySummary(int $userId, string $range)
    {
        if (!Schema::hasTable('tomtatsuckhoehangngay')) {
            return collect();
        }

        $now = now('Asia/Ho_Chi_Minh');
        $query = DB::table('tomtatsuckhoehangngay')
            ->where('NguoiDungID', $userId)
            ->where(function ($q) {
                $q->where('TongCaloRa', '>', 0)
                    ->orWhere('ThoiGianHoatDong', '>', 0);
            });

        if ($range === 'year') {
            $query->whereYear('Ngay', $now->year);
            $label = 'MONTH(Ngay)';
        } elseif ($range === 'month') {
            $query->whereYear('Ngay', $now->year)->whereMonth('Ngay', $now->month);
            $label = 'DATE(Ngay)';
        } else {
            $query->whereDate('Ngay', '>=', $now->copy()->startOfWeek()->toDateString())
                ->whereDate('Ngay', '<=', $now->copy()->endOfWeek()->toDateString());
            $label = 'DATE(Ngay)';
        }

        return $query
            ->selectRaw("$label as label")
            ->selectRaw("'Tổng ngày' as ten_bai_tap")
            ->selectRaw('SUM(CASE WHEN TongCaloRa > 0 OR ThoiGianHoatDong > 0 THEN 1 ELSE 0 END) as so_lan')
            ->selectRaw('SUM(ThoiGianHoatDong) as tong_phut')
            ->selectRaw('SUM(TongCaloRa) as tong_kcal')
            ->selectRaw('MAX(TongCaloRa) as max_kcal')
            ->groupByRaw($label)
            ->orderByRaw($label)
            ->get();
    }

    public function store(Request $request, DailySummaryService $summaryService, HealthRiskEngineService $riskEngine)
    {
        $validated = $request->validate([
            'NguoiDungID' => 'required_without:user_id|integer|exists:taikhoan,ID',
            'user_id' => 'required_without:NguoiDungID|integer|exists:taikhoan,ID',
            'HoatDongID' => 'nullable|integer',
            'TenHoatDong' => 'nullable|string|max:255',
            'DurationMinutes' => 'nullable|numeric|min:0',
            'ThoiLuongPhut' => 'nullable|numeric|min:0',
            'CaloriesBurned' => 'nullable|numeric|min:0',
            'CaloriesDot' => 'nullable|numeric|min:0',
            'DistanceKm' => 'nullable|numeric|min:0',
            'Steps' => 'nullable|integer|min:0',
            'LoaiHoatDong' => 'nullable|string|max:100',
            'MucDo' => 'nullable|string|max:50',
            'NgayHoatDong' => 'nullable|date',
        ]);

        $userId = $validated['NguoiDungID'] ?? $validated['user_id'];
        $duration = $validated['DurationMinutes'] ?? $validated['ThoiLuongPhut'] ?? 0;
        $calories = $validated['CaloriesBurned'] ?? $validated['CaloriesDot'] ?? 0;
        $date = $validated['NgayHoatDong'] ?? now('Asia/Ho_Chi_Minh')->toDateString();
        $rawLevel = $validated['MucDo'] ?? null;
        $level = match ($rawLevel) {
            'Nhẹ', 'Trung bình', 'Nặng' => $rawLevel,
            'Relax', 'Beginner', 'Easy' => 'Nhẹ',
            'Power', 'Intense', 'Hard' => 'Nặng',
            default => 'Trung bình',
        };

        if (!Schema::hasTable('activity_logs') && Schema::hasTable('lichhoatdong')) {
            $activityId = $validated['HoatDongID'] ?? null;
            if (!$activityId || !DB::table('hoatdong')->where('ID', $activityId)->exists()) {
                $activityId = DB::table('hoatdong')->insertGetId([
                    'TenHoatDong' => $validated['TenHoatDong'] ?? 'Hoạt động',
                    'Calo' => $calories,
                    'MoTa' => $validated['LoaiHoatDong'] ?? null,
                ]);
            }
            $start = "{$date} 00:00:00";
            $end = Carbon::parse($start)->addMinutes((int) $duration)->toDateTimeString();
            $scheduleId = DB::table('lichhoatdong')->insertGetId([
                'NguoiDungID' => $userId,
                'HoatDongID' => $activityId,
                'ThoiGianBatDau' => $start,
                'ThoiGianKetThuc' => $end,
                'TrangThai' => 'HoanThanh',
            ]);
            DB::table('chitiethoatdong')->insert([
                'LichHoatDongID' => $scheduleId,
                'CaloDot' => $calories,
                'SoBuoc' => $validated['Steps'] ?? null,
                'QuangDuong' => $validated['DistanceKm'] ?? null,
            ]);
            try {
                $summaryService->refresh((int) $userId, $date);
            } catch (\Throwable $e) {
                // Legacy schema without activity_logs/daily summaries can still save the activity record.
            }
            try {
                $riskEngine->evaluateAfterActivity((int) $userId, $date, (int) $scheduleId);
            } catch (\Throwable $e) {
                // Risk checks must never block saving activity logs.
            }
            return response()->json([
                'success' => true,
                'data' => [
                    'ID' => $scheduleId,
                    'NguoiDungID' => $userId,
                    'TenHoatDong' => $validated['TenHoatDong'] ?? 'Hoạt động',
                    'ThoiLuongPhut' => $duration,
                    'CaloriesDot' => $calories,
                    'NgayHoatDong' => $date,
                    'LoaiHoatDong' => $validated['LoaiHoatDong'] ?? 'Phổ biến',
                    'MucDo' => $level,
                ],
            ], 201);
        }

        $activity = ActivityLog::create([
            'NguoiDungID' => $userId,
            'TenHoatDong' => $validated['TenHoatDong'] ?? null,
            'LoaiHoatDong' => $validated['LoaiHoatDong'] ?? $rawLevel,
            'ThoiLuongPhut' => $duration,
            'CaloriesDot' => $calories,
            'DistanceKm' => $validated['DistanceKm'] ?? null,
            'Steps' => $validated['Steps'] ?? null,
            'MucDo' => $level,
            'NgayHoatDong' => $date,
            'GioBatDau' => now('Asia/Ho_Chi_Minh')->toTimeString(),
            'Nguon' => 'manual',
            'CreatedAt' => now('Asia/Ho_Chi_Minh'),
            'IsCompleted' => true,
        ]);

        try {
            $summaryService->refresh((int) $userId, $date);
        } catch (\Throwable $e) {
            // Activity is saved even if optional summary tables are missing or out of sync.
        }
        try {
            $riskEngine->evaluateAfterActivity((int) $userId, $date, (int) $activity->ID);
        } catch (\Throwable $e) {
            // Risk checks must never block saving activity logs.
        }

        return response()->json([
            'success' => true,
            'data' => $activity,
        ], 201);
    }

    public function destroy($id, DailySummaryService $summaryService)
    {
        if (!Schema::hasTable('activity_logs') && Schema::hasTable('lichhoatdong')) {
            $row = DB::table('lichhoatdong')->where('ID', $id)->first();
            if (!$row) {
                return response()->json(['success' => false, 'message' => 'Khong tim thay hoat dong'], 404);
            }

            DB::table('chitiethoatdong')->where('LichHoatDongID', $id)->delete();
            DB::table('lichhoatdong')->where('ID', $id)->delete();

            try {
                $summaryService->refresh((int) $row->NguoiDungID, Carbon::parse($row->ThoiGianBatDau)->toDateString());
            } catch (\Throwable $e) {
                // Activity is deleted even when optional summary refresh is unavailable.
            }

            return response()->json(['success' => true]);
        }

        $activity = ActivityLog::query()->where('ID', $id)->first();
        if (!$activity) {
            return response()->json(['success' => false, 'message' => 'Khong tim thay hoat dong'], 404);
        }

        $userId = (int) $activity->NguoiDungID;
        $date = (string) $activity->NgayHoatDong;
        $activity->delete();

        try {
            $summaryService->refresh($userId, $date);
        } catch (\Throwable $e) {
            // Activity is deleted even when optional summary refresh is unavailable.
        }

        return response()->json(['success' => true]);
    }
}
