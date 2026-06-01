<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserGoalController extends Controller
{
    private const WATER_TYPES = ['UongNuoc', 'Nuoc', 'Uong nuoc', 'water'];
    private const ACTIVITY_TYPES = ['VanDong', 'LuyenTap', 'HoatDong', 'activity', 'exercise'];

    public function show(Request $request)
    {
        $userId = $request->integer('NguoiDungID') ?: $request->integer('user_id') ?: $request->integer('nguoi_dung_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Thiếu người dùng'], 422);
        }

        if (Schema::hasTable('muctieunguoidung')) {
            $data = DB::table('muctieunguoidung')->where('NguoiDungID', $userId)->get();
        } elseif (Schema::hasTable('user_goals')) {
            $data = DB::table('user_goals')->where('NguoiDungID', $userId)->get();
        } elseif (Schema::hasTable('muctieusuckhoe')) {
            $data = DB::table('muctieusuckhoe')->where('NguoiDungID', $userId)->get();
        } else {
            $data = collect();
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'NguoiDungID' => 'required_without:user_id|integer|exists:taikhoan,ID',
            'user_id' => 'required_without:NguoiDungID|integer|exists:taikhoan,ID',
            'Loai' => 'nullable|string|max:100',
            'LoaiMucTieu' => 'nullable|string|max:100',
            'TenMucTieu' => 'nullable|string|max:255',
            'GiaTri' => 'nullable|numeric',
            'GiaTriMucTieu' => 'nullable|numeric',
            'DonVi' => 'nullable|string|max:50',
            'DonViDo' => 'nullable|string|max:50',
            'ChuKyLap' => 'nullable|string|max:50',
            'BatNhac' => 'nullable',
            'GioNhac' => 'nullable|string|max:20',
            'NgayTrongTuan' => 'nullable|string|max:50',
        ]);

        $userId = $data['NguoiDungID'] ?? $data['user_id'];
        $type = $data['Loai'] ?? $data['LoaiMucTieu'] ?? 'TongQuat';
        $value = $data['GiaTri'] ?? $data['GiaTriMucTieu'] ?? null;

        if (Schema::hasTable('muctieunguoidung')) {
            $payload = [
                'GiaTri' => $value,
                'DonVi' => $data['DonVi'] ?? $data['DonViDo'] ?? null,
                'ChuKyLap' => $data['ChuKyLap'] ?? 'HangNgay',
                'BatNhac' => array_key_exists('BatNhac', $data) ? (bool) $data['BatNhac'] : false,
                'GioNhac' => $data['GioNhac'] ?? null,
                'NgayTrongTuan' => $data['NgayTrongTuan'] ?? '1,2,3,4,5,6,7',
                'NgayCapNhat' => now('Asia/Ho_Chi_Minh'),
            ];
            $goalExists = DB::table('muctieunguoidung')
                ->where('NguoiDungID', $userId)
                ->where('Loai', $type)
                ->exists();
            if (!$goalExists && Schema::hasColumn('muctieunguoidung', 'NgayTao')) {
                $payload['NgayTao'] = now('Asia/Ho_Chi_Minh');
            }

            DB::table('muctieunguoidung')->updateOrInsert(
                ['NguoiDungID' => $userId, 'Loai' => $type],
                $payload
            );

            $saved = DB::table('muctieunguoidung')
                ->where('NguoiDungID', $userId)
                ->where('Loai', $type)
                ->first();
        } elseif (Schema::hasTable('user_goals')) {
            $payload = [
                'GiaTri' => $value,
            ];
            foreach ([
                'DonVi' => $data['DonVi'] ?? $data['DonViDo'] ?? null,
                'ChuKyLap' => $data['ChuKyLap'] ?? null,
                'BatNhac' => array_key_exists('BatNhac', $data) ? (bool) $data['BatNhac'] : null,
                'GioNhac' => $data['GioNhac'] ?? null,
                'NgayTrongTuan' => $data['NgayTrongTuan'] ?? null,
                'NgayCapNhat' => now('Asia/Ho_Chi_Minh'),
            ] as $column => $columnValue) {
                if ($columnValue !== null && Schema::hasColumn('user_goals', $column)) {
                    $payload[$column] = $columnValue;
                }
            }
            $goalExists = DB::table('user_goals')
                ->where('NguoiDungID', $userId)
                ->where('Loai', $type)
                ->exists();
            if (!$goalExists && Schema::hasColumn('user_goals', 'NgayTao')) {
                $payload['NgayTao'] = now('Asia/Ho_Chi_Minh');
            }

            DB::table('user_goals')->updateOrInsert(
                ['NguoiDungID' => $userId, 'Loai' => $type],
                $payload
            );

            $saved = DB::table('user_goals')
                ->where('NguoiDungID', $userId)
                ->where('Loai', $type)
                ->first();
        } elseif (Schema::hasTable('muctieusuckhoe')) {
            DB::table('muctieusuckhoe')->updateOrInsert(
                ['NguoiDungID' => $userId, 'LoaiMucTieu' => $type],
                [
                    'TenMucTieu' => $data['TenMucTieu'] ?? $type,
                    'GiaTriMucTieu' => $value,
                    'NgayBatDau' => now('Asia/Ho_Chi_Minh')->toDateString(),
                    'TrangThai' => 'DangThucHien',
                    'DonViDo' => $data['DonViDo'] ?? $data['DonVi'] ?? null,
                ]
            );

            $saved = DB::table('muctieusuckhoe')
                ->where('NguoiDungID', $userId)
                ->where('LoaiMucTieu', $type)
                ->first();
        } else {
            $saved = null;
        }

        return response()->json([
            'success' => true,
            'data' => $saved,
            'muc_tieu_ml' => (float) ($saved->GiaTri ?? $saved->GiaTriMucTieu ?? $value ?? 0),
        ]);
    }

    public function suggestions(Request $request)
    {
        $data = $request->validate([
            'NguoiDungID' => 'required_without:user_id|integer|exists:taikhoan,ID',
            'user_id' => 'required_without:NguoiDungID|integer|exists:taikhoan,ID',
            'days' => 'nullable|integer|min:7|max:30',
            'to' => 'nullable|date',
            'notify' => 'nullable|boolean',
        ]);

        $userId = (int) ($data['NguoiDungID'] ?? $data['user_id']);
        $days = (int) ($data['days'] ?? 7);
        $to = $data['to'] ?? now('Asia/Ho_Chi_Minh')->toDateString();
        $from = now('Asia/Ho_Chi_Minh')->parse($to)->subDays($days - 1)->toDateString();

        $suggestions = array_values(array_filter([
            $this->waterSuggestion($userId, $from, $to, $days),
            $this->activitySuggestion($userId, $from, $to, $days),
        ]));

        $shouldNotify = (bool) ($data['notify'] ?? true);
        if ($shouldNotify && !empty($suggestions)) {
            $this->notifyGoalSuggestions($userId, $suggestions);
        }

        return response()->json([
            'success' => true,
            'range' => [
                'from' => $from,
                'to' => $to,
                'days' => $days,
            ],
            'message' => empty($suggestions)
                ? 'Chua can dieu chinh muc tieu. He thong se tiep tuc theo doi tien do cua ban.'
                : 'Co de xuat dieu chinh muc tieu. Nguoi dung can xac nhan truoc khi cap nhat.',
            'data' => $suggestions,
        ]);
    }

    public function applySuggestion(Request $request)
    {
        $data = $request->validate([
            'NguoiDungID' => 'required_without:user_id|integer|exists:taikhoan,ID',
            'user_id' => 'required_without:NguoiDungID|integer|exists:taikhoan,ID',
            'Loai' => 'required|string|max:100',
            'GiaTri' => 'required|numeric|min:1',
            'DonVi' => 'nullable|string|max:50',
        ]);

        $userId = (int) ($data['NguoiDungID'] ?? $data['user_id']);
        $response = $this->store(new Request([
            'NguoiDungID' => $userId,
            'Loai' => $data['Loai'],
            'GiaTri' => $data['GiaTri'],
            'DonVi' => $data['DonVi'] ?? $this->unitForType($data['Loai']),
            'ChuKyLap' => 'HangNgay',
            'BatNhac' => true,
        ]));

        $this->createNotification(
            $userId,
            'GoalUpdated',
            'Muc tieu ' . $data['Loai'] . ' da duoc cap nhat sau khi ban xac nhan: ' . $data['GiaTri'] . ' ' . ($data['DonVi'] ?? $this->unitForType($data['Loai'])) . '.'
        );

        return $response;
    }

    private function waterSuggestion(int $userId, string $from, string $to, int $days): ?array
    {
        $goal = $this->goalValue($userId, self::WATER_TYPES) ?: 2000;
        $rows = $this->dailyWaterRows($userId, $from, $to);
        $metrics = $this->completionMetrics($rows, $goal, $days);

        if ($metrics['completion_rate'] >= 0.85 && $metrics['average'] >= $goal * 1.05) {
            $suggested = min(3500, $this->roundStep($goal * 1.1, 50));
            if ($suggested > $goal) {
                return $this->suggestionPayload('UongNuoc', $goal, $suggested, 'ml', $metrics, 'Ban thuong xuyen dat muc tieu nuoc. Co the tang nhe muc tieu neu ban cam thay phu hop.');
            }
        }

        if ($metrics['completion_rate'] < 0.4 && $metrics['average'] > 0 && $goal > 1200) {
            $suggested = max(1200, $this->roundStep(max($metrics['average'] * 1.2, $goal * 0.8), 50));
            if ($suggested < $goal) {
                return $this->suggestionPayload('UongNuoc', $goal, $suggested, 'ml', $metrics, 'Tien do uong nuoc con thap. Nen giam nhe muc tieu de de hoan thanh hon roi tang lai sau.');
            }
        }

        return null;
    }

    private function activitySuggestion(int $userId, string $from, string $to, int $days): ?array
    {
        $goal = $this->goalValue($userId, self::ACTIVITY_TYPES) ?: 30;
        $rows = $this->dailyActivityRows($userId, $from, $to);
        $metrics = $this->completionMetrics($rows, $goal, $days);

        if ($metrics['completion_rate'] >= 0.8 && $metrics['average'] >= $goal * 1.05) {
            $suggested = min(90, $this->roundStep($goal + 5, 5));
            if ($suggested > $goal) {
                return $this->suggestionPayload('VanDong', $goal, $suggested, 'phut', $metrics, 'Ban dang duy tri van dong tot. Co the tang nhe muc tieu moi ngay neu co the trang phu hop.');
            }
        }

        if ($metrics['completion_rate'] < 0.4 && $metrics['average'] > 0 && $goal > 10) {
            $suggested = max(10, $this->roundStep(max($metrics['average'] * 1.2, $goal * 0.75), 5));
            if ($suggested < $goal) {
                return $this->suggestionPayload('VanDong', $goal, $suggested, 'phut', $metrics, 'Tien do van dong con thap. Nen dat muc tieu nho hon de tao thoi quen truoc.');
            }
        }

        return null;
    }

    private function suggestionPayload(string $type, float $current, float $suggested, string $unit, array $metrics, string $reason): array
    {
        return [
            'loai' => $type,
            'current_goal' => $current,
            'suggested_goal' => $suggested,
            'unit' => $unit,
            'completion_rate' => round($metrics['completion_rate'] * 100, 1),
            'average' => round($metrics['average'], 1),
            'completed_days' => $metrics['completed_days'],
            'total_days' => $metrics['total_days'],
            'reason' => $reason,
            'requires_user_confirmation' => true,
        ];
    }

    private function completionMetrics(array $rows, float $goal, int $days): array
    {
        $completed = 0;
        $total = 0;
        foreach ($rows as $value) {
            $value = (float) $value;
            $total += $value;
            if ($value >= $goal) {
                $completed++;
            }
        }

        return [
            'completed_days' => $completed,
            'total_days' => $days,
            'completion_rate' => $days > 0 ? $completed / $days : 0,
            'average' => $days > 0 ? $total / $days : 0,
        ];
    }

    private function dailyWaterRows(int $userId, string $from, string $to): array
    {
        $rows = DB::table('theodoinuoc')
            ->where('NguoiDungID', $userId)
            ->whereDate('Ngay', '>=', $from)
            ->whereDate('Ngay', '<=', $to)
            ->selectRaw('DATE(Ngay) as date, SUM(LuongNuoc) as total')
            ->groupByRaw('DATE(Ngay)')
            ->pluck('total', 'date')
            ->all();

        return $this->dateSeriesValues($from, $to, $rows);
    }

    private function dailyActivityRows(int $userId, string $from, string $to): array
    {
        if (Schema::hasTable('tomtatsuckhoehangngay')) {
            $rows = DB::table('tomtatsuckhoehangngay')
                ->where('NguoiDungID', $userId)
                ->whereDate('Ngay', '>=', $from)
                ->whereDate('Ngay', '<=', $to)
                ->selectRaw('DATE(Ngay) as date, SUM(ThoiGianHoatDong) as total')
                ->groupByRaw('DATE(Ngay)')
                ->pluck('total', 'date')
                ->all();
            if (array_sum(array_map('floatval', $rows)) > 0) {
                return $this->dateSeriesValues($from, $to, $rows);
            }
        }

        $rows = DB::table('lichhoatdong')
            ->where('NguoiDungID', $userId)
            ->whereDate('ThoiGianBatDau', '>=', $from)
            ->whereDate('ThoiGianBatDau', '<=', $to)
            ->selectRaw('DATE(ThoiGianBatDau) as date')
            ->selectRaw('SUM(TIMESTAMPDIFF(MINUTE, ThoiGianBatDau, ThoiGianKetThuc)) as total')
            ->groupByRaw('DATE(ThoiGianBatDau)')
            ->pluck('total', 'date')
            ->all();

        return $this->dateSeriesValues($from, $to, $rows);
    }

    private function dateSeriesValues(string $from, string $to, array $rows): array
    {
        $values = [];
        $cursor = now('Asia/Ho_Chi_Minh')->parse($from);
        $end = now('Asia/Ho_Chi_Minh')->parse($to);
        while ($cursor->lte($end)) {
            $key = $cursor->toDateString();
            $values[] = (float) ($rows[$key] ?? 0);
            $cursor->addDay();
        }

        return $values;
    }

    private function goalValue(int $userId, array $types): ?float
    {
        if (Schema::hasTable('muctieunguoidung')) {
            $goal = DB::table('muctieunguoidung')
                ->where('NguoiDungID', $userId)
                ->whereIn('Loai', $types)
                ->latest('ID')
                ->value('GiaTri');
            if ($goal) {
                return (float) $goal;
            }
        }

        if (Schema::hasTable('muctieusuckhoe')) {
            $goal = DB::table('muctieusuckhoe')
                ->where('NguoiDungID', $userId)
                ->where(function ($query) use ($types) {
                    $query->whereIn('LoaiMucTieu', $types);
                    foreach ($types as $type) {
                        $query->orWhere('TenMucTieu', 'like', '%' . $type . '%');
                    }
                })
                ->whereNotNull('GiaTriMucTieu')
                ->latest('ID')
                ->value('GiaTriMucTieu');
            if ($goal) {
                return (float) $goal;
            }
        }

        return null;
    }

    private function notifyGoalSuggestions(int $userId, array $suggestions): void
    {
        foreach ($suggestions as $suggestion) {
            $content = "De xuat dieu chinh muc tieu {$suggestion['loai']}: tu {$suggestion['current_goal']} {$suggestion['unit']} sang {$suggestion['suggested_goal']} {$suggestion['unit']}. {$suggestion['reason']} Ban can xac nhan truoc khi cap nhat.";
            $this->createNotification($userId, 'GoalSuggestion', $content);
        }
    }

    private function createNotification(int $userId, string $type, string $content): void
    {
        if (!Schema::hasTable('thongbao')) {
            return;
        }

        $exists = DB::table('thongbao')
            ->where('NguoiDungID', $userId)
            ->where('LoaiThongBao', $type)
            ->where('NoiDung', $content)
            ->where('ThoiGian', '>=', now('Asia/Ho_Chi_Minh')->subDays(3))
            ->exists();
        if ($exists) {
            return;
        }

        DB::table('thongbao')->insert([
            'NguoiDungID' => $userId,
            'LoaiThongBao' => $type,
            'ThoiGian' => now('Asia/Ho_Chi_Minh'),
            'NoiDung' => $content,
            'TrangThaiGui' => 'DaGui',
            'DaDoc' => 0,
        ]);
    }

    private function roundStep(float $value, int $step): float
    {
        return round($value / $step) * $step;
    }

    private function unitForType(string $type): string
    {
        return in_array($type, self::WATER_TYPES, true) ? 'ml' : 'phut';
    }
}
