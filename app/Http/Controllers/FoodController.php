<?php

namespace App\Http\Controllers;

use App\Service\DailySummaryService;
use App\Service\HealthRiskEngineService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FoodController extends Controller
{
    public function index(Request $request)
    {
        $query = DB::table('thucpham');

        if ($request->filled('q')) {
            $keyword = trim((string) $request->query('q'));
            $query->where(function ($q) use ($keyword) {
                $q->where('Ten', 'like', "%{$keyword}%");
                if (Schema::hasColumn('thucpham', 'Keywords')) {
                    $q->orWhere('Keywords', 'like', "%{$keyword}%");
                }
            });
        }

        if ($request->filled('LoaiThucPham') && Schema::hasColumn('thucpham', 'LoaiThucPham')) {
            $query->where('LoaiThucPham', $request->query('LoaiThucPham'));
        }

        return response()->json([
            'success' => true,
            'data' => $query->orderBy('Ten')->limit(200)->get()->map(fn ($food) => $this->formatFood($food))->values(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'Ten' => 'required|string|max:255',
            'Calo' => 'nullable|numeric|min:0',
            'Protein' => 'nullable|numeric|min:0',
            'Carb' => 'nullable|numeric|min:0',
            'ChatBeo' => 'nullable|numeric|min:0',
            'DonVi' => 'nullable|string|max:50',
            'KhoiLuongGram' => 'nullable|numeric|min:0',
            'LoaiThucPham' => 'nullable|string|max:100',
            'Keywords' => 'nullable|string|max:500',
            'IsHealthy' => 'nullable',
        ]);

        $insert = [
            'Ten' => $data['Ten'],
            'Calo' => $data['Calo'] ?? 0,
            'Protein' => $data['Protein'] ?? 0,
            'Carb' => $data['Carb'] ?? 0,
            'ChatBeo' => $data['ChatBeo'] ?? 0,
            'DonVi' => $data['DonVi'] ?? 'g',
            'KhoiLuongGram' => $data['KhoiLuongGram'] ?? 100,
        ];

        foreach (['LoaiThucPham', 'Keywords', 'IsHealthy'] as $column) {
            if (Schema::hasColumn('thucpham', $column)) {
                $insert[$column] = $data[$column] ?? ($column === 'IsHealthy' ? 1 : null);
            }
        }
        if (Schema::hasColumn('thucpham', 'CreatedAt')) {
            $insert['CreatedAt'] = now('Asia/Ho_Chi_Minh');
        }

        $id = DB::table('thucpham')->insertGetId($insert);
        $food = DB::table('thucpham')->where('ID', $id)->first();

        return response()->json([
            'success' => true,
            'data' => $this->formatFood($food),
        ], 201);
    }

    public function meals(Request $request)
    {
        $userId = $request->integer('NguoiDungID') ?: $request->integer('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Thiếu người dùng'], 422);
        }

        $date = $request->query('Ngay') ?: $request->query('date') ?: now('Asia/Ho_Chi_Minh')->toDateString();

        $meals = DB::table('buaan')
            ->where('NguoiDungID', $userId)
            ->whereDate('Ngay', $date)
            ->orderBy('ID')
            ->get();

        $detailsByMeal = collect();
        if ($meals->isNotEmpty()) {
            $detailsByMeal = DB::table('chitietbuaan as c')
                ->leftJoin('thucpham as t', 't.ID', '=', 'c.ThucPhamID')
                ->whereIn('c.BuaAnID', $meals->pluck('ID')->all())
                ->orderBy('c.ID')
                ->get($this->detailSelectColumns())
                ->groupBy('BuaAnID');
        }

        $data = $meals->map(function ($meal) use ($detailsByMeal) {
            $mealDetails = ($detailsByMeal->get($meal->ID) ?? collect())
                ->map(fn ($detail) => $this->formatMealDetail($detail))
                ->values();

            return [
                'ID' => (int) $meal->ID,
                'NguoiDungID' => (int) $meal->NguoiDungID,
                'Ngay' => Carbon::parse($meal->Ngay)->toDateString(),
                'LoaiBuaAn' => $meal->LoaiBuaAn,
                'chi_tiet' => $mealDetails,
            ];
        })->values();

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function storeMeal(Request $request, DailySummaryService $summaryService, HealthRiskEngineService $riskEngine)
    {
        $data = $request->validate([
            'NguoiDungID' => 'required_without:user_id|integer|exists:taikhoan,ID',
            'user_id' => 'required_without:NguoiDungID|integer|exists:taikhoan,ID',
            'ThucPhamID' => 'required|integer|exists:thucpham,ID',
            'LoaiBuaAn' => 'nullable|string|max:100',
            'SoLuong' => 'nullable|numeric|min:0.01',
            'Ngay' => 'nullable|date',
        ]);

        $userId = $data['NguoiDungID'] ?? $data['user_id'];
        $date = $data['Ngay'] ?? now('Asia/Ho_Chi_Minh')->toDateString();
        $mealType = $this->normalizeMealType($data['LoaiBuaAn'] ?? 'Snack');
        $quantity = (float) ($data['SoLuong'] ?? 100);

        $food = DB::table('thucpham')->where('ID', $data['ThucPhamID'])->first();
        if (!$food) {
            return response()->json(['success' => false, 'message' => 'Không tìm thấy thực phẩm'], 404);
        }

        $meal = DB::table('buaan')
            ->where('NguoiDungID', $userId)
            ->whereDate('Ngay', $date)
            ->where('LoaiBuaAn', $mealType)
            ->first();

        if (!$meal) {
            $mealPayload = [
                'NguoiDungID' => $userId,
                'Ngay' => $date,
                'LoaiBuaAn' => $mealType,
            ];
            foreach ([
                'TongCalories' => 0,
                'TongProtein' => 0,
                'TongCarb' => 0,
                'TongFat' => 0,
                'MucTieu' => $this->dailyCalorieGoal((int) $userId),
                'CreatedAt' => now('Asia/Ho_Chi_Minh'),
            ] as $column => $value) {
                if (Schema::hasColumn('buaan', $column)) {
                    $mealPayload[$column] = $value;
                }
            }
            $mealId = DB::table('buaan')->insertGetId($mealPayload);
        } else {
            $mealId = (int) $meal->ID;
        }

        $calories = $this->scaledValue($food->Calo ?? 0, $quantity);
        $protein = $this->scaledValue($food->Protein ?? 0, $quantity);
        $carb = $this->scaledValue($food->Carb ?? 0, $quantity);
        $fat = $this->scaledValue($food->ChatBeo ?? 0, $quantity);

        $insert = [
            'BuaAnID' => $mealId,
            'ThucPhamID' => (int) $food->ID,
            'SoLuong' => $quantity,
            'TongCalo' => $calories,
            'TongProtein' => $protein,
        ];

        $optionalColumns = [
            'TongCarb' => $carb,
            'TongFat' => $fat,
            'DonVi' => $food->DonVi ?? 'g',
            'CaloriesMoi100g' => $food->Calo ?? 0,
        ];
        foreach ($optionalColumns as $column => $value) {
            if (Schema::hasColumn('chitietbuaan', $column)) {
                $insert[$column] = $value;
            }
        }

        $detailId = DB::table('chitietbuaan')->insertGetId($insert);
        $detail = DB::table('chitietbuaan as c')
            ->leftJoin('thucpham as t', 't.ID', '=', 'c.ThucPhamID')
            ->where('c.ID', $detailId)
            ->first($this->detailSelectColumns());

        $this->refreshMealTotals($mealId);
        try {
            $summaryService->refresh((int) $userId, $date);
        } catch (\Throwable $e) {
            // Meal is saved even if optional summary tables are missing or out of sync.
        }
        try {
            $riskEngine->evaluateAfterMeal((int) $userId, $date, (int) $mealId);
        } catch (\Throwable $e) {
            // Risk checks must never block saving meals.
        }

        return response()->json([
            'success' => true,
            'data' => [
                'meal_id' => $mealId,
                'detail' => $this->formatMealDetail($detail),
            ],
        ], 201);
    }

    public function deleteMealDetail(int $id, DailySummaryService $summaryService)
    {
        $detail = DB::table('chitietbuaan as c')
            ->join('buaan as b', 'b.ID', '=', 'c.BuaAnID')
            ->where('c.ID', $id)
            ->first([
                'c.ID',
                'c.BuaAnID',
                'b.NguoiDungID',
                'b.Ngay',
            ]);

        if (!$detail) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy món ăn',
            ], 404);
        }

        DB::transaction(function () use ($detail) {
            DB::table('chitietbuaan')->where('ID', $detail->ID)->delete();
            $this->refreshMealTotals((int) $detail->BuaAnID);
        });

        try {
            $summaryService->refresh(
                (int) $detail->NguoiDungID,
                Carbon::parse($detail->Ngay)->toDateString()
            );
        } catch (\Throwable $e) {
            // Detail is deleted even if optional summary tables are unavailable.
        }

        return response()->json(['success' => true]);
    }

    public function stats(Request $request)
    {
        $userId = $request->integer('NguoiDungID') ?: $request->integer('user_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Thiếu người dùng'], 422);
        }

        $range = $request->query('range', 'week');
        $now = now('Asia/Ho_Chi_Minh');
        $query = DB::table('buaan as b')
            ->leftJoin('chitietbuaan as c', 'c.BuaAnID', '=', 'b.ID')
            ->leftJoin('thucpham as t', 't.ID', '=', 'c.ThucPhamID')
            ->where('b.NguoiDungID', $userId)
            ->whereNotNull('c.ID');

        if ($range === 'year') {
            $query->whereYear('b.Ngay', $now->year);
            $label = 'MONTH(b.Ngay)';
        } elseif ($range === 'month') {
            $query->whereYear('b.Ngay', $now->year)->whereMonth('b.Ngay', $now->month);
            $label = 'DATE(b.Ngay)';
        } else {
            $query->whereDate('b.Ngay', '>=', $now->copy()->startOfWeek()->toDateString())
                ->whereDate('b.Ngay', '<=', $now->copy()->endOfWeek()->toDateString());
            $label = 'DATE(b.Ngay)';
        }

        $calorieExpr = $this->nutritionExpr('TongCalo', 'Calo');
        $proteinExpr = $this->nutritionExpr('TongProtein', 'Protein');
        $carbExpr = $this->nutritionExpr('TongCarb', 'Carb');
        $fatExpr = $this->nutritionExpr('TongFat', 'ChatBeo');

        $rows = $query
            ->selectRaw("$label as label")
            ->selectRaw("COUNT(DISTINCT b.ID) as so_bua")
            ->selectRaw("SUM($calorieExpr) as tong_kcal")
            ->selectRaw("SUM($proteinExpr) as protein")
            ->selectRaw("SUM($carbExpr) as carb")
            ->selectRaw("SUM($fatExpr) as fat")
            ->groupByRaw($label)
            ->orderByRaw($label)
            ->get()
            ->map(function ($row) use ($range) {
                $label = $row->label;
                if ($range !== 'year' && $label) {
                    $label = Carbon::parse($label)->toDateString();
                }

                return [
                    'label' => (string) $label,
                    'so_bua' => (int) $row->so_bua,
                    'tong_kcal' => round((float) $row->tong_kcal, 1),
                    'protein' => round((float) $row->protein, 1),
                    'carb' => round((float) $row->carb, 1),
                    'fat' => round((float) $row->fat, 1),
                ];
            })
            ->values();

        if ((float) $rows->sum('tong_kcal') <= 0) {
            $summaryRows = $this->nutritionRowsFromDailySummary($userId, $range);
            if ($summaryRows->isNotEmpty()) {
                $rows = $summaryRows;
            }
        }

        return response()->json([
            'success' => true,
            'range' => $range,
            'summary' => [
                'tong_kcal' => round((float) $rows->sum('tong_kcal'), 1),
                'so_bua' => (int) $rows->sum('so_bua'),
                'muc_tieu_kcal' => $this->dailyCalorieGoal($userId),
                'protein' => round((float) $rows->sum('protein'), 1),
                'carb' => round((float) $rows->sum('carb'), 1),
                'fat' => round((float) $rows->sum('fat'), 1),
            ],
            'data' => $rows,
        ]);
    }

    private function nutritionRowsFromDailySummary(int $userId, string $range)
    {
        if (!Schema::hasTable('tomtatsuckhoehangngay')) {
            return collect();
        }

        $now = now('Asia/Ho_Chi_Minh');
        $query = DB::table('tomtatsuckhoehangngay')
            ->where('NguoiDungID', $userId)
            ->where(function ($q) {
                $q->where('TongCaloVao', '>', 0)
                    ->orWhere('TongProtein', '>', 0)
                    ->orWhere('TongCarb', '>', 0)
                    ->orWhere('TongChatBeo', '>', 0);
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
            ->selectRaw('SUM(TongCaloVao) as tong_kcal')
            ->selectRaw('SUM(TongProtein) as protein')
            ->selectRaw('SUM(TongCarb) as carb')
            ->selectRaw('SUM(TongChatBeo) as fat')
            ->selectRaw('SUM(CASE WHEN TongCaloVao > 0 THEN 1 ELSE 0 END) as so_bua')
            ->groupByRaw($label)
            ->orderByRaw($label)
            ->get()
            ->map(function ($row) use ($range) {
                $label = $row->label;
                if ($range !== 'year' && $label) {
                    $label = Carbon::parse($label)->toDateString();
                }

                return [
                    'label' => (string) $label,
                    'so_bua' => (int) $row->so_bua,
                    'tong_kcal' => round((float) $row->tong_kcal, 1),
                    'protein' => round((float) $row->protein, 1),
                    'carb' => round((float) $row->carb, 1),
                    'fat' => round((float) $row->fat, 1),
                ];
            })
            ->values();
    }

    private function formatFood($food): array
    {
        return [
            'ID' => (int) ($food->ID ?? 0),
            'Ten' => $food->Ten ?? '',
            'Calo' => (float) ($food->Calo ?? 0),
            'Protein' => (float) ($food->Protein ?? 0),
            'Carb' => (float) ($food->Carb ?? 0),
            'ChatBeo' => (float) ($food->ChatBeo ?? 0),
            'DonVi' => $food->DonVi ?? 'g',
            'KhoiLuongGram' => (float) ($food->KhoiLuongGram ?? 100),
            'LoaiThucPham' => $food->LoaiThucPham ?? '',
            'Keywords' => $food->Keywords ?? '',
            'IsHealthy' => $food->IsHealthy ?? true,
        ];
    }

    private function formatMealDetail($detail): array
    {
        $quantity = (float) ($detail->SoLuong ?? 0);
        $food = (object) [
            'ID' => $detail->ThucPhamID ?? 0,
            'Ten' => $detail->Ten ?? 'Món ăn',
            'Calo' => $detail->Calo ?? 0,
            'Protein' => $detail->Protein ?? 0,
            'Carb' => $detail->Carb ?? 0,
            'ChatBeo' => $detail->ChatBeo ?? 0,
            'DonVi' => $detail->FoodDonVi ?? 'g',
            'KhoiLuongGram' => $detail->KhoiLuongGram ?? 100,
            'LoaiThucPham' => $detail->LoaiThucPham ?? '',
            'Keywords' => $detail->Keywords ?? '',
            'IsHealthy' => $detail->IsHealthy ?? true,
        ];

        return [
            'ID' => (int) ($detail->ID ?? 0),
            'BuaAnID' => (int) ($detail->BuaAnID ?? 0),
            'ThucPhamID' => (int) ($detail->ThucPhamID ?? 0),
            'SoLuong' => $quantity,
            'DonVi' => $detail->DonVi ?? $detail->FoodDonVi ?? 'g',
            'TongCalo' => (float) ($detail->TongCalo ?? $this->scaledValue($food->Calo, $quantity)),
            'TongProtein' => (float) ($detail->TongProtein ?? $this->scaledValue($food->Protein, $quantity)),
            'TongCarb' => (float) ($detail->TongCarb ?? $this->scaledValue($food->Carb, $quantity)),
            'TongFat' => (float) ($detail->TongFat ?? $this->scaledValue($food->ChatBeo, $quantity)),
            'CaloriesMoi100g' => (float) ($detail->CaloriesMoi100g ?? $food->Calo),
            'Ten' => $food->Ten,
            'LoaiThucPham' => $food->LoaiThucPham,
            'thuc_pham' => $this->formatFood($food),
        ];
    }

    private function detailSelectColumns(): array
    {
        $columns = [
            'c.ID',
            'c.BuaAnID',
            'c.ThucPhamID',
            'c.SoLuong',
            'c.TongCalo',
            'c.TongProtein',
            't.Ten',
            't.Calo',
            't.Protein',
            't.Carb',
            't.ChatBeo',
            't.DonVi as FoodDonVi',
            't.KhoiLuongGram',
        ];

        foreach ([
            'TongCarb' => 'c.TongCarb',
            'TongFat' => 'c.TongFat',
            'DonVi' => 'c.DonVi',
            'CaloriesMoi100g' => 'c.CaloriesMoi100g',
        ] as $column => $select) {
            if (Schema::hasColumn('chitietbuaan', $column)) {
                $columns[] = $select;
            }
        }
        foreach ([
            'LoaiThucPham' => 't.LoaiThucPham',
            'Keywords' => 't.Keywords',
            'IsHealthy' => 't.IsHealthy',
        ] as $column => $select) {
            if (Schema::hasColumn('thucpham', $column)) {
                $columns[] = $select;
            }
        }

        return $columns;
    }

    private function nutritionExpr(string $detailColumn, string $foodColumn): string
    {
        $detailExpr = Schema::hasColumn('chitietbuaan', $detailColumn)
            ? "c.$detailColumn"
            : 'NULL';

        return "COALESCE($detailExpr, (COALESCE(t.$foodColumn, 0) * COALESCE(c.SoLuong, 0) / 100), 0)";
    }

    private function scaledValue($per100g, float $quantity): float
    {
        return round(((float) $per100g) * $quantity / 100, 2);
    }

    private function normalizeMealType(string $value): string
    {
        $lower = mb_strtolower($value);
        $ascii = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower) ?: $lower;

        if (str_contains($lower, 'sáng') || str_contains($ascii, 'sang')) {
            return 'Sang';
        }
        if (str_contains($lower, 'trưa') || str_contains($ascii, 'trua')) {
            return 'Trua';
        }
        if (str_contains($lower, 'tối') || str_contains($ascii, 'toi')) {
            return 'Toi';
        }

        return 'Snack';
    }

    private function refreshMealTotals(int $mealId): void
    {
        $updates = [];
        foreach ([
            'TongCalories' => 'TongCalo',
            'TongProtein' => 'TongProtein',
            'TongCarb' => 'TongCarb',
            'TongFat' => 'TongFat',
        ] as $mealColumn => $detailColumn) {
            if (Schema::hasColumn('buaan', $mealColumn) && Schema::hasColumn('chitietbuaan', $detailColumn)) {
                $updates[$mealColumn] = (float) DB::table('chitietbuaan')
                    ->where('BuaAnID', $mealId)
                    ->sum($detailColumn);
            }
        }

        if ($updates) {
            DB::table('buaan')->where('ID', $mealId)->update($updates);
        }
    }

    private function dailyCalorieGoal(int $userId): int
    {
        if (Schema::hasTable('user_goals')) {
            $goal = DB::table('user_goals')
                ->where('NguoiDungID', $userId)
                ->whereIn('Loai', ['DinhDuong', 'Calories', 'Calo'])
                ->value('GiaTri');

            if ($goal) {
                return (int) $goal;
            }
        }
        if (Schema::hasTable('muctieusuckhoe')) {
            $goal = DB::table('muctieusuckhoe')
                ->where('NguoiDungID', $userId)
                ->where(function ($query) {
                    $query
                        ->whereIn('LoaiMucTieu', ['DinhDuong', 'Calories', 'Calo', 'AnUong'])
                        ->orWhere('TenMucTieu', 'like', '%ăn%')
                        ->orWhere('TenMucTieu', 'like', '%calo%')
                        ->orWhere('TenMucTieu', 'like', '%calories%');
                })
                ->whereNotNull('GiaTriMucTieu')
                ->latest('ID')
                ->value('GiaTriMucTieu');

            if ($goal) {
                return (int) $goal;
            }
        }

        return 2000;
    }
}
