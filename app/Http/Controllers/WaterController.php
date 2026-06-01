<?php
namespace App\Http\Controllers;

use App\Service\DailySummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class WaterController extends Controller
{
    public function getWaterByQuery(Request $request)
    {
        $userId = $request->integer('NguoiDungID') ?: $request->integer('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Missing user id'], 422);
        }

        $date = $request->query('Ngay') ?: $request->query('date') ?: now('Asia/Ho_Chi_Minh')->toDateString();
        $total = DB::table('theodoinuoc')
            ->where('NguoiDungID', $userId)
            ->whereDate('Ngay', $date)
            ->sum('LuongNuoc');

        $latestId = DB::table('theodoinuoc')
            ->where('NguoiDungID', $userId)
            ->whereDate('Ngay', $date)
            ->orderByDesc('ID')
            ->value('ID');

        return response()->json([
            'ID' => $latestId ?? 0,
            'NguoiDungID' => $userId,
            'Ngay' => $date,
            'LuongNuoc' => (float) $total,
            'muc_tieu_ml' => $this->waterGoal((int) $userId),
        ]);
    }

    public function getWater(Request $request, $id)
    {
        $today = $request->query('date', now('Asia/Ho_Chi_Minh')->toDateString());
        $total = DB::table('theodoinuoc')
            ->where('NguoiDungID', $id)
            ->whereDate('Ngay', $today)
            ->sum('LuongNuoc');

        $records = DB::table('theodoinuoc')
            ->where('NguoiDungID', $id)
            ->whereDate('Ngay', $today)
            ->orderByDesc('ID')
            ->get();

        return response()->json([
            'LuongNuoc' => $total,
            'muc_tieu_ml' => $this->waterGoal((int) $id),
            'lich_su' => $records,
        ]);
    }

    public function addWater(Request $request, DailySummaryService $summaryService)
    {
        $data = $request->validate([
            'NguoiDungID' => 'required|integer|exists:taikhoan,ID',
            'LuongNuoc' => 'required|numeric|min:0.01',
            'Ngay' => 'nullable|date',
            'LoaiNuoc' => 'nullable|string|max:100',
            'SoLuongGoc' => 'nullable|numeric|min:0',
        ]);

        $date = $data['Ngay'] ?? now('Asia/Ho_Chi_Minh')->toDateString();

        $insert = [
            'NguoiDungID' => $data['NguoiDungID'],
            'LuongNuoc' => $data['LuongNuoc'],
            'Ngay' => $date,
        ];
        if (Schema::hasColumn('theodoinuoc', 'LoaiNuoc')) {
            $insert['LoaiNuoc'] = $data['LoaiNuoc'] ?? 'Nước lọc';
        }
        if (Schema::hasColumn('theodoinuoc', 'SoLuongGoc')) {
            $insert['SoLuongGoc'] = $data['SoLuongGoc'] ?? $data['LuongNuoc'];
        }
        if (Schema::hasColumn('theodoinuoc', 'GioUong')) {
            $insert['GioUong'] = now('Asia/Ho_Chi_Minh')->toTimeString();
        }

        $id = DB::table('theodoinuoc')->insertGetId($insert);

        try {
            $summaryService->refresh((int) $data['NguoiDungID'], $date);
        } catch (\Throwable $e) {
            // Legacy databases may not have summary tables yet; water is still saved.
        }

        $total = DB::table('theodoinuoc')
            ->where('NguoiDungID', $data['NguoiDungID'])
            ->whereDate('Ngay', $date)
            ->sum('LuongNuoc');

        return response()->json([
            'success' => true,
            'total' => $total,
            'data' => DB::table('theodoinuoc')->where('ID', $id)->first(),
        ]);
    }

    public function deleteWater(Request $request, $id, DailySummaryService $summaryService)
    {
        $row = DB::table('theodoinuoc')->where('ID', $id)->first();
        if (!$row) {
            return response()->json(['success' => false, 'message' => 'Khong tim thay lan uong'], 404);
        }

        DB::table('theodoinuoc')->where('ID', $id)->delete();

        try {
            $summaryService->refresh((int) $row->NguoiDungID, (string) $row->Ngay);
        } catch (\Throwable $e) {
            // Water history was deleted even if optional summary refresh is unavailable.
        }

        $total = DB::table('theodoinuoc')
            ->where('NguoiDungID', $row->NguoiDungID)
            ->whereDate('Ngay', $row->Ngay)
            ->sum('LuongNuoc');

        return response()->json([
            'success' => true,
            'total' => $total,
        ]);
    }

    public function stats(Request $request, $id)
    {
        $range = $request->query('range', 'week');
        $now = now('Asia/Ho_Chi_Minh');
        $query = DB::table('theodoinuoc')->where('NguoiDungID', $id);

        if ($range === 'year') {
            $rows = $query
                ->whereYear('Ngay', $now->year)
                ->selectRaw('MONTH(Ngay) as label, COUNT(*) as so_lan, SUM(LuongNuoc) as tong_ml')
                ->groupByRaw('MONTH(Ngay)')
                ->orderByRaw('MONTH(Ngay)')
                ->get();
        } elseif ($range === 'month') {
            $rows = $query
                ->whereYear('Ngay', $now->year)
                ->whereMonth('Ngay', $now->month)
                ->selectRaw('DATE(Ngay) as label, COUNT(*) as so_lan, SUM(LuongNuoc) as tong_ml')
                ->groupByRaw('DATE(Ngay)')
                ->orderByRaw('DATE(Ngay)')
                ->get();
        } else {
            $start = $now->copy()->startOfWeek();
            $rows = $query
                ->whereDate('Ngay', '>=', $start->toDateString())
                ->whereDate('Ngay', '<=', $now->copy()->endOfWeek()->toDateString())
                ->selectRaw('DATE(Ngay) as label, COUNT(*) as so_lan, SUM(LuongNuoc) as tong_ml')
                ->groupByRaw('DATE(Ngay)')
                ->orderByRaw('DATE(Ngay)')
                ->get();
        }

        if ((float) $rows->sum('tong_ml') <= 0) {
            $summaryRows = $this->waterRowsFromDailySummary((int) $id, $range);
            if ($summaryRows->isNotEmpty()) {
                $rows = $summaryRows;
            }
        }

        return response()->json([
            'success' => true,
            'range' => $range,
            'summary' => [
                'so_lan' => (int) $rows->sum('so_lan'),
                'tong_ml' => (float) $rows->sum('tong_ml'),
                'muc_tieu_ml' => $this->waterGoal((int) $id),
            ],
            'data' => $rows,
        ]);
    }

    private function waterRowsFromDailySummary(int $userId, string $range)
    {
        if (!Schema::hasTable('tomtatsuckhoehangngay')) {
            return collect();
        }

        $now = now('Asia/Ho_Chi_Minh');
        $query = DB::table('tomtatsuckhoehangngay')
            ->where('NguoiDungID', $userId)
            ->where('TongLuongNuoc', '>', 0);

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
            ->selectRaw('COUNT(*) as so_lan')
            ->selectRaw('SUM(TongLuongNuoc) as tong_ml')
            ->groupByRaw($label)
            ->orderByRaw($label)
            ->get();
    }

    private function waterGoal(int $userId): int
    {
        if (Schema::hasTable('muctieunguoidung')) {
            $goal = DB::table('muctieunguoidung')
                ->where('NguoiDungID', $userId)
                ->whereIn('Loai', ['UongNuoc', 'Nuoc', 'Uong nuoc', 'water'])
                ->latest('ID')
                ->value('GiaTri');
            if ($goal) return (int) $goal;
        }
        if (Schema::hasTable('user_goals')) {
            $goal = DB::table('user_goals')
                ->where('NguoiDungID', $userId)
                ->where('Loai', 'UongNuoc')
                ->value('GiaTri');
            if ($goal) return (int) $goal;
        }
        if (Schema::hasTable('muctieusuckhoe')) {
            $goal = DB::table('muctieusuckhoe')
                ->where('NguoiDungID', $userId)
                ->where(function ($query) {
                    $query
                        ->whereIn('LoaiMucTieu', ['Nuoc', 'UongNuoc', 'Uong nuoc'])
                        ->orWhere('TenMucTieu', 'like', '%nước%')
                        ->orWhere('TenMucTieu', 'like', '%nuoc%');
                })
                ->whereNotNull('GiaTriMucTieu')
                ->latest('ID')
                ->value('GiaTriMucTieu');
            if ($goal) return (int) $goal;
        }
        return 1500;
    }
}
