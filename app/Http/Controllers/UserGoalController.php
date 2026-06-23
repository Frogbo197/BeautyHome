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
            'NgayBatDau' => 'nullable|date',
            'NgayKetThuc' => 'nullable|date|after_or_equal:NgayBatDau',
            'TrangThai' => 'nullable|string|in:DangTheoDoi,HoanThanh,KhongDat,DaDieuChinh,TamDung',
            'LyDo' => 'nullable|string|max:500',
            'NguonThayDoi' => 'nullable|string|max:50',
        ]);

        $userId = $data['NguoiDungID'] ?? $data['user_id'];
        $type = $data['Loai'] ?? $data['LoaiMucTieu'] ?? 'TongQuat';
        $value = $data['GiaTri'] ?? $data['GiaTriMucTieu'] ?? null;
        $startDate = $data['NgayBatDau'] ?? now('Asia/Ho_Chi_Minh')->toDateString();
        $endDate = $data['NgayKetThuc'] ?? now('Asia/Ho_Chi_Minh')->addDays(6)->toDateString();
        $status = $data['TrangThai'] ?? 'DangTheoDoi';
        $reason = $data['LyDo'] ?? null;
        $source = $data['NguonThayDoi'] ?? 'User';

        if (Schema::hasTable('muctieunguoidung')) {
            $existing = DB::table('muctieunguoidung')
                ->where('NguoiDungID', $userId)
                ->where('Loai', $type)
                ->first();
            $payload = $this->onlyExistingColumns('muctieunguoidung', [
                'GiaTri' => $value,
                'DonVi' => $data['DonVi'] ?? $data['DonViDo'] ?? null,
                'ChuKyLap' => $data['ChuKyLap'] ?? 'HangNgay',
                'BatNhac' => array_key_exists('BatNhac', $data) ? (bool) $data['BatNhac'] : false,
                'GioNhac' => $data['GioNhac'] ?? null,
                'NgayTrongTuan' => $data['NgayTrongTuan'] ?? '1,2,3,4,5,6,7',
                'NgayBatDau' => $startDate,
                'NgayKetThuc' => $endDate,
                'TrangThai' => $status,
                'NgayCapNhat' => now('Asia/Ho_Chi_Minh'),
            ]);
            if (!$existing && Schema::hasColumn('muctieunguoidung', 'NgayTao')) {
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
            $this->recordGoalHistory($userId, $type, $existing, $saved, $source, $reason);
        } elseif (Schema::hasTable('user_goals')) {
            $existing = DB::table('user_goals')
                ->where('NguoiDungID', $userId)
                ->where('Loai', $type)
                ->first();
            $payload = [
                'GiaTri' => $value,
            ];
            foreach ([
                'DonVi' => $data['DonVi'] ?? $data['DonViDo'] ?? null,
                'ChuKyLap' => $data['ChuKyLap'] ?? null,
                'BatNhac' => array_key_exists('BatNhac', $data) ? (bool) $data['BatNhac'] : null,
                'GioNhac' => $data['GioNhac'] ?? null,
                'NgayTrongTuan' => $data['NgayTrongTuan'] ?? null,
                'NgayBatDau' => $startDate,
                'NgayKetThuc' => $endDate,
                'TrangThai' => $status,
                'NgayCapNhat' => now('Asia/Ho_Chi_Minh'),
            ] as $column => $columnValue) {
                if ($columnValue !== null && Schema::hasColumn('user_goals', $column)) {
                    $payload[$column] = $columnValue;
                }
            }
            if (!$existing && Schema::hasColumn('user_goals', 'NgayTao')) {
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
            $this->recordGoalHistory($userId, $type, $existing, $saved, $source, $reason);
        } elseif (Schema::hasTable('muctieusuckhoe')) {
            $existing = DB::table('muctieusuckhoe')
                ->where('NguoiDungID', $userId)
                ->where('LoaiMucTieu', $type)
                ->first();
            DB::table('muctieusuckhoe')->updateOrInsert(
                ['NguoiDungID' => $userId, 'LoaiMucTieu' => $type],
                $this->onlyExistingColumns('muctieusuckhoe', [
                    'TenMucTieu' => $data['TenMucTieu'] ?? $type,
                    'GiaTriMucTieu' => $value,
                    'NgayBatDau' => $startDate,
                    'NgayKetThuc' => $endDate,
                    'TrangThai' => $status,
                    'DonViDo' => $data['DonViDo'] ?? $data['DonVi'] ?? null,
                ])
            );

            $saved = DB::table('muctieusuckhoe')
                ->where('NguoiDungID', $userId)
                ->where('LoaiMucTieu', $type)
                ->first();
            $this->recordGoalHistory($userId, $type, $existing, $saved, $source, $reason);
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
        $progress = $this->goalProgressRows($userId);

        $shouldNotify = (bool) ($data['notify'] ?? true);
        if ($shouldNotify && !empty($suggestions)) {
            $this->notifyGoalSuggestions($userId, $suggestions);
        }
        if ($shouldNotify && !empty($progress)) {
            $this->notifyGoalDeadlines($userId, $progress);
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
            'progress' => $progress,
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
            'NgayBatDau' => 'nullable|date',
            'NgayKetThuc' => 'nullable|date|after_or_equal:NgayBatDau',
        ]);

        $userId = (int) ($data['NguoiDungID'] ?? $data['user_id']);
        $startDate = $data['NgayBatDau'] ?? now('Asia/Ho_Chi_Minh')->toDateString();
        $endDate = $data['NgayKetThuc'] ?? now('Asia/Ho_Chi_Minh')->addDays(6)->toDateString();
        $response = $this->store(new Request([
            'NguoiDungID' => $userId,
            'Loai' => $data['Loai'],
            'GiaTri' => $data['GiaTri'],
            'DonVi' => $data['DonVi'] ?? $this->unitForType($data['Loai']),
            'ChuKyLap' => 'HangNgay',
            'BatNhac' => true,
            'NgayBatDau' => $startDate,
            'NgayKetThuc' => $endDate,
            'TrangThai' => 'DangTheoDoi',
            'NguonThayDoi' => 'GoalSuggestion',
            'LyDo' => 'Nguoi dung xac nhan de xuat dieu chinh muc tieu',
        ]));

        $this->createNotification(
            $userId,
            'GoalUpdated',
            'Muc tieu ' . $data['Loai'] . ' da duoc cap nhat sau khi ban xac nhan: ' . $data['GiaTri'] . ' ' . ($data['DonVi'] ?? $this->unitForType($data['Loai'])) . '.'
        );

        return $response;
    }

    public function progress(Request $request)
    {
        $data = $request->validate([
            'NguoiDungID' => 'required_without:user_id|integer|exists:taikhoan,ID',
            'user_id' => 'required_without:NguoiDungID|integer|exists:taikhoan,ID',
            'from' => 'nullable|date',
            'to' => 'nullable|date|after_or_equal:from',
            'notify' => 'nullable|boolean',
        ]);

        $userId = (int) ($data['NguoiDungID'] ?? $data['user_id']);
        $progress = $this->goalProgressRows($userId, $data['from'] ?? null, $data['to'] ?? null);
        if ((bool) ($data['notify'] ?? true)) {
            $this->notifyGoalDeadlines($userId, $progress);
        }

        return response()->json([
            'success' => true,
            'data' => $progress,
        ]);
    }

    public function history(Request $request)
    {
        $data = $request->validate([
            'NguoiDungID' => 'required_without:user_id|integer|exists:taikhoan,ID',
            'user_id' => 'required_without:NguoiDungID|integer|exists:taikhoan,ID',
            'Loai' => 'nullable|string|max:100',
            'limit' => 'nullable|integer|min:1|max:100',
        ]);

        $userId = (int) ($data['NguoiDungID'] ?? $data['user_id']);
        if (!Schema::hasTable('muctieulichsu')) {
            return response()->json(['success' => true, 'data' => []]);
        }

        $query = DB::table('muctieulichsu')
            ->where('NguoiDungID', $userId)
            ->orderByDesc('ID');
        if (!empty($data['Loai'])) {
            $query->where('Loai', $data['Loai']);
        }

        return response()->json([
            'success' => true,
            'data' => $query->limit((int) ($data['limit'] ?? 30))->get(),
        ]);
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

    private function goalProgressRows(int $userId, ?string $overrideFrom = null, ?string $overrideTo = null): array
    {
        $goals = $this->currentGoalRows($userId);
        $rows = [];
        foreach ($goals as $goal) {
            $rows[] = $this->progressForGoal($userId, $goal, $overrideFrom, $overrideTo);
        }

        return $rows;
    }

    private function currentGoalRows(int $userId)
    {
        if (Schema::hasTable('muctieunguoidung')) {
            return DB::table('muctieunguoidung')
                ->where('NguoiDungID', $userId)
                ->orderBy('Loai')
                ->get();
        }

        if (Schema::hasTable('user_goals')) {
            return DB::table('user_goals')
                ->where('NguoiDungID', $userId)
                ->orderBy('Loai')
                ->get();
        }

        if (Schema::hasTable('muctieusuckhoe')) {
            return DB::table('muctieusuckhoe')
                ->where('NguoiDungID', $userId)
                ->orderBy('LoaiMucTieu')
                ->get();
        }

        return collect();
    }

    private function progressForGoal(int $userId, object $goal, ?string $overrideFrom = null, ?string $overrideTo = null): array
    {
        $type = $goal->Loai ?? $goal->LoaiMucTieu ?? 'TongQuat';
        $value = (float) ($goal->GiaTri ?? $goal->GiaTriMucTieu ?? 0);
        $unit = $goal->DonVi ?? $goal->DonViDo ?? $this->unitForType($type);
        $hasDeadline = !empty($goal->NgayKetThuc);
        $start = $overrideFrom
            ?: ($goal->NgayBatDau ?? now('Asia/Ho_Chi_Minh')->subDays(6)->toDateString());
        $end = $overrideTo
            ?: ($goal->NgayKetThuc ?? now('Asia/Ho_Chi_Minh')->toDateString());
        $days = max(1, now('Asia/Ho_Chi_Minh')->parse($start)->diffInDays(now('Asia/Ho_Chi_Minh')->parse($end)) + 1);

        $metrics = [
            'completed_days' => null,
            'total_days' => $days,
            'completion_rate' => 0,
            'average' => null,
        ];

        if ($value > 0 && in_array($type, self::WATER_TYPES, true)) {
            $metrics = $this->completionMetrics($this->dailyWaterRows($userId, $start, $end), $value, $days);
        } elseif ($value > 0 && in_array($type, self::ACTIVITY_TYPES, true)) {
            $metrics = $this->completionMetrics($this->dailyActivityRows($userId, $start, $end), $value, $days);
        }

        $completionPercent = round(((float) $metrics['completion_rate']) * 100, 1);
        $deadline = $this->deadlineStatus($end, $completionPercent, $hasDeadline);

        return [
            'id' => $goal->ID ?? null,
            'loai' => $type,
            'goal' => $value,
            'unit' => $unit,
            'ngay_bat_dau' => $start,
            'ngay_ket_thuc' => $goal->NgayKetThuc ?? null,
            'has_deadline' => $hasDeadline,
            'trang_thai' => $goal->TrangThai ?? 'DangTheoDoi',
            'completed_days' => $metrics['completed_days'],
            'total_days' => $metrics['total_days'],
            'completion_percent' => $completionPercent,
            'average' => $metrics['average'] === null ? null : round((float) $metrics['average'], 1),
            'deadline_status' => $deadline['status'],
            'days_remaining' => $deadline['days_remaining'],
            'requires_user_confirmation' => false,
        ];
    }

    private function deadlineStatus(string $endDate, float $completionPercent, bool $hasDeadline): array
    {
        if (!$hasDeadline) {
            return ['status' => 'KhongCoHan', 'days_remaining' => null];
        }

        $today = now('Asia/Ho_Chi_Minh')->startOfDay();
        $end = now('Asia/Ho_Chi_Minh')->parse($endDate)->startOfDay();
        $daysRemaining = $today->diffInDays($end, false);

        if ($completionPercent >= 100) {
            return ['status' => 'HoanThanh', 'days_remaining' => $daysRemaining];
        }
        if ($daysRemaining < 0) {
            return ['status' => 'QuaHan', 'days_remaining' => $daysRemaining];
        }
        if ($daysRemaining <= 2) {
            return ['status' => 'SapHetHan', 'days_remaining' => $daysRemaining];
        }

        return ['status' => 'DangTheoDoi', 'days_remaining' => $daysRemaining];
    }

    private function notifyGoalDeadlines(int $userId, array $progressRows): void
    {
        foreach ($progressRows as $row) {
            if (!$row['has_deadline']) {
                continue;
            }

            if ($row['deadline_status'] === 'SapHetHan') {
                $content = "Muc tieu {$row['loai']} se het han sau {$row['days_remaining']} ngay. Tien do hien tai {$row['completion_percent']}%.";
                $this->createNotification($userId, 'GoalDeadline', $content);
            }

            if ($row['deadline_status'] === 'QuaHan') {
                $content = "Muc tieu {$row['loai']} da qua han. Tien do dat {$row['completion_percent']}%. Ban co the dieu chinh muc tieu moi neu can.";
                $this->createNotification($userId, 'GoalOverdue', $content);
            }
        }
    }

    private function recordGoalHistory(int $userId, string $type, ?object $oldGoal, ?object $newGoal, string $source, ?string $reason): void
    {
        if (!$newGoal || !Schema::hasTable('muctieulichsu')) {
            return;
        }

        $oldValue = $oldGoal ? (float) ($oldGoal->GiaTri ?? $oldGoal->GiaTriMucTieu ?? 0) : null;
        $newValue = (float) ($newGoal->GiaTri ?? $newGoal->GiaTriMucTieu ?? 0);
        $oldEnd = $oldGoal->NgayKetThuc ?? null;
        $newEnd = $newGoal->NgayKetThuc ?? null;
        $oldStatus = $oldGoal->TrangThai ?? null;
        $newStatus = $newGoal->TrangThai ?? 'DangTheoDoi';

        if ($oldGoal && $oldValue === $newValue && $oldEnd === $newEnd && $oldStatus === $newStatus) {
            return;
        }

        DB::table('muctieulichsu')->insert($this->onlyExistingColumns('muctieulichsu', [
            'NguoiDungID' => $userId,
            'MucTieuID' => $newGoal->ID ?? null,
            'Loai' => $type,
            'GiaTriCu' => $oldValue,
            'GiaTriMoi' => $newValue,
            'DonVi' => $newGoal->DonVi ?? $newGoal->DonViDo ?? $this->unitForType($type),
            'NgayBatDau' => $newGoal->NgayBatDau ?? null,
            'NgayKetThuc' => $newEnd,
            'TrangThai' => $newStatus,
            'NguonThayDoi' => $source,
            'LyDo' => $reason,
            'created_at' => now('Asia/Ho_Chi_Minh'),
            'updated_at' => now('Asia/Ho_Chi_Minh'),
        ]));
    }

    private function onlyExistingColumns(string $table, array $payload): array
    {
        return array_filter(
            $payload,
            fn ($value, $column) => Schema::hasColumn($table, $column),
            ARRAY_FILTER_USE_BOTH
        );
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
