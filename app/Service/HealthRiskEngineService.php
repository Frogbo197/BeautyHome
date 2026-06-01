<?php

namespace App\Service;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthRiskEngineService
{
    private const WATER_TYPES = ['UongNuoc', 'Nuoc', 'Uong nuoc', 'water'];
    private const ACTIVITY_TYPES = ['VanDong', 'LuyenTap', 'HoatDong', 'activity', 'exercise'];

    public function evaluateAfterWater(int $userId, ?string $date = null, ?int $sourceId = null): array
    {
        $date = $date ?: now('Asia/Ho_Chi_Minh')->toDateString();
        return array_merge(
            $this->evaluateWater($userId, $date, $sourceId),
            $this->evaluatePositiveStreak($userId, $date)
        );
    }

    public function evaluateAfterActivity(int $userId, ?string $date = null, ?int $sourceId = null): array
    {
        $date = $date ?: now('Asia/Ho_Chi_Minh')->toDateString();
        return array_merge(
            $this->evaluateActivity($userId, $date, $sourceId),
            $this->evaluatePositiveStreak($userId, $date)
        );
    }

    public function evaluateAfterMeal(int $userId, ?string $date = null, ?int $sourceId = null): array
    {
        $date = $date ?: now('Asia/Ho_Chi_Minh')->toDateString();
        return $this->evaluateNutrition($userId, $date, $sourceId);
    }

    public function evaluateAfterMedication(int $userId, ?string $date = null, ?int $sourceId = null): array
    {
        $date = $date ?: now('Asia/Ho_Chi_Minh')->toDateString();
        return $this->evaluateMedication($userId, $date, $sourceId);
    }

    public function evaluateAfterWeight(int $userId, ?int $sourceId = null): array
    {
        return $this->evaluateWeight($userId, $sourceId);
    }

    private function evaluateWater(int $userId, string $date, ?int $sourceId): array
    {
        if (!Schema::hasTable('theodoinuoc')) {
            return [];
        }

        $goal = $this->goalValue($userId, self::WATER_TYPES) ?: $this->estimatedWaterGoal($userId);
        $today = $this->dailyWaterTotal($userId, $date);
        $events = [];

        if ($today > max(5000, $goal * 2.2)) {
            $events[] = $this->recordEvent($userId, 'water_high_day', [
                'title' => 'Luong nuoc hom nay cao bat thuong',
                'message' => "Hom nay ban da ghi nhan khoang {$today} ml nuoc. Hay kiem tra lai du lieu va uong theo nhu cau co the.",
                'action' => 'Neu ban khat bat thuong, met moi, phu, dau dau hoac co benh nen, hay lien he nhan vien y te.',
                'source_table' => 'theodoinuoc',
                'source_id' => $sourceId,
                'metadata' => ['date' => $date, 'total_ml' => $today, 'goal_ml' => $goal],
            ]);
        }

        $window = $this->dateWindow($date, 3);
        $lowDays = 0;
        foreach ($window as $day) {
            $total = $this->dailyWaterTotal($userId, $day);
            if ($total > 0 && $total < $goal * 0.6) {
                $lowDays++;
            }
        }
        if ($lowDays >= 3) {
            $events[] = $this->recordEvent($userId, 'water_low_trend', [
                'title' => 'Ban da uong it nuoc lien tiep 3 ngay',
                'message' => 'Co the bat dau bang mot ly nuoc nho va chia deu trong ngay. Minh se nhac nhe de ban de theo hon.',
                'action' => 'Bo sung nuoc theo nhu cau co the; neu co benh than/tim mach hay han che dich, hay theo huong dan bac si.',
                'source_table' => 'theodoinuoc',
                'source_id' => $sourceId,
                'metadata' => ['date' => $date, 'low_days' => $lowDays, 'goal_ml' => $goal],
            ]);
        }

        return array_filter($events);
    }

    private function evaluateActivity(int $userId, string $date, ?int $sourceId): array
    {
        $today = $this->dailyActivityMinutes($userId, $date);
        $goal = $this->goalValue($userId, self::ACTIVITY_TYPES) ?: 30;
        $events = [];

        $window = $this->dateWindow($date, 3);
        $lowDays = 0;
        foreach ($window as $day) {
            $minutes = $this->dailyActivityMinutes($userId, $day);
            if ($minutes < max(10, $goal * 0.35)) {
                $lowDays++;
            }
        }
        if ($lowDays >= 3) {
            $events[] = $this->recordEvent($userId, 'activity_low_trend', [
                'title' => 'Van dong hoi it trong vai ngay gan day',
                'message' => 'Di bo them 10 phut hom nay la mot buoc rat on roi. Minh nhac nhe thoi, khong can ep qua suc nha.',
                'action' => 'Bat dau bang van dong nhe; neu dau nguc, kho tho, chong mat thi dung lai va tim ho tro y te.',
                'source_table' => 'lichhoatdong',
                'source_id' => $sourceId,
                'metadata' => ['date' => $date, 'low_days' => $lowDays, 'goal_minutes' => $goal],
            ]);
        }

        $baseline = $this->averageActivityMinutes($userId, $date, 7);
        if ($today >= 180 && ($baseline <= 0 || $today >= max(180, $baseline * 2.5))) {
            $events[] = $this->recordEvent($userId, 'activity_high_day', [
                'title' => 'Van dong hom nay cao hon binh thuong',
                'message' => "Hom nay ban ghi nhan {$today} phut van dong. Hay nghi giua buoi va theo doi cam giac co the.",
                'action' => 'Neu co dau nguc, kho tho, chong mat, ngat hoac dau bat thuong, hay lien he nhan vien y te.',
                'source_table' => 'lichhoatdong',
                'source_id' => $sourceId,
                'metadata' => ['date' => $date, 'minutes' => $today, 'baseline_minutes' => $baseline],
            ]);
        }

        return array_filter($events);
    }

    private function evaluateMedication(int $userId, string $date, ?int $sourceId): array
    {
        if (!Schema::hasTable('lichdungthuoc')) {
            return [];
        }

        $events = [];
        $todayRows = $this->medicationRows($userId, $date);
        $takenByMedicine = $todayRows
            ->filter(fn ($row) => in_array((string) ($row->TrangThai ?? ''), ['da_uong', 'DaUong', 'Da uong'], true))
            ->groupBy('ThuocID');

        foreach ($takenByMedicine as $medicineId => $rows) {
            $first = $rows->first();
            $limit = (int) ($first->SoLanMoiNgay ?? $first->TanSuat ?? 0);
            $limit = max($limit, 4);
            $isRiskGroup = $this->isHighRiskMedicationGroup((string) ($first->NhomThuoc ?? $first->LoaiThuoc ?? ''));
            $total = $rows->count();
            if ($total > $limit) {
                $events[] = $this->recordEvent($userId, 'medication_over_schedule', [
                    'title' => 'Canh bao dung thuoc vuot lich da thiet lap',
                    'message' => 'He thong ghi nhan ' . ($first->TenThuoc ?: 'mot loai thuoc') . " da duoc danh dau uong {$total} lan trong ngay.",
                    'action' => 'Vui long kiem tra lai thuoc vua su dung. Neu co nguy co uong nham/qua lieu hoac co trieu chung bat thuong, hay lien he nhan vien y te ngay.',
                    'source_table' => 'lichdungthuoc',
                    'source_id' => $sourceId,
                    'metadata' => ['date' => $date, 'medicine_id' => $medicineId, 'taken_count' => $total, 'configured_limit' => $limit, 'risk_group' => $isRiskGroup],
                    'score_delta' => $isRiskGroup ? 10 : 0,
                ]);
            }

            $recent = $rows->filter(function ($row) {
                $time = $row->ThoiGianUongThucTe ?? $row->ThoiGian ?? null;
                if (!$time) {
                    return false;
                }
                return Carbon::parse($time, 'Asia/Ho_Chi_Minh')->gte(now('Asia/Ho_Chi_Minh')->subHours(2));
            })->count();
            if ($recent >= 3) {
                $events[] = $this->recordEvent($userId, 'medication_repeated_short_time', [
                    'title' => 'Canh bao dung thuoc lap lai trong thoi gian ngan',
                    'message' => 'He thong ghi nhan ' . ($first->TenThuoc ?: 'mot loai thuoc') . " duoc danh dau uong {$recent} lan trong 2 gio gan day.",
                    'action' => 'Hay kiem tra lai lich uong. Neu co nguy co uong nham/qua lieu, lien he nhan vien y te hoac co so y te gan nhat.',
                    'source_table' => 'lichdungthuoc',
                    'source_id' => $sourceId,
                    'metadata' => ['date' => $date, 'medicine_id' => $medicineId, 'recent_count' => $recent, 'risk_group' => $isRiskGroup],
                    'score_delta' => $isRiskGroup ? 10 : 0,
                ]);
            }
        }

        $missed = DB::table('lichdungthuoc')
            ->where('NguoiDungID', $userId)
            ->whereDate('ThoiGian', '>=', Carbon::parse($date)->subDays(6)->toDateString())
            ->whereDate('ThoiGian', '<=', $date)
            ->whereIn('TrangThai', ['bo_lo', 'BoLo', 'missed'])
            ->count();
        if ($missed >= 2) {
            $events[] = $this->recordEvent($userId, 'medication_missed_trend', [
                'title' => 'Ban bo lo nhieu lan uong thuoc gan day',
                'message' => "Trong 7 ngay gan day co {$missed} lan uong thuoc bi bo lo. Viec dung thuoc khong deu co the lam giam hieu qua dieu tri.",
                'action' => 'Hay kiem tra lai lich nhac va trao doi voi bac si/duoc si neu ban thuong xuyen quen thuoc.',
                'source_table' => 'lichdungthuoc',
                'source_id' => $sourceId,
                'metadata' => ['date' => $date, 'missed_count' => $missed],
            ]);
        }

        return array_filter($events);
    }

    private function evaluateNutrition(int $userId, string $date, ?int $sourceId): array
    {
        if (!Schema::hasTable('buaan')) {
            return [];
        }

        $events = [];
        $today = $this->dailyCalories($userId, $date);
        if ($today > 4000) {
            $events[] = $this->recordEvent($userId, 'calorie_high_day', [
                'title' => 'Luong calo hom nay cao hon binh thuong',
                'message' => "Hom nay ban ghi nhan khoang {$today} kcal. Hay kiem tra lai khau phan va du lieu vua nhap.",
                'action' => 'Uu tien bua tiep theo nhe hon, nhieu rau va uong du nuoc.',
                'source_table' => 'buaan',
                'source_id' => $sourceId,
                'metadata' => ['date' => $date, 'calories' => $today],
            ]);
        }

        $lowDays = 0;
        foreach ($this->dateWindow($date, 2) as $day) {
            $kcal = $this->dailyCalories($userId, $day);
            if ($kcal > 0 && $kcal < 600) {
                $lowDays++;
            }
        }
        if ($lowDays >= 2) {
            $events[] = $this->recordEvent($userId, 'calorie_low_trend', [
                'title' => 'Luong calo nap vao thap trong vai ngay',
                'message' => 'Ban ghi nhan calo kha thap lien tiep. Hay dam bao bua an co du nang luong, protein va rau qua.',
                'action' => 'Neu sut can, met moi, choang vang hoac dang co benh nen, hay tham khao y kien nhan vien y te.',
                'source_table' => 'buaan',
                'source_id' => $sourceId,
                'metadata' => ['date' => $date, 'low_days' => $lowDays],
            ]);
        }

        return array_filter($events);
    }

    private function evaluateWeight(int $userId, ?int $sourceId): array
    {
        if (!Schema::hasTable('chisosuckhoe') || !Schema::hasColumn('chisosuckhoe', 'CanNang')) {
            return [];
        }

        $rows = DB::table('chisosuckhoe')
            ->where('NguoiDungID', $userId)
            ->whereNotNull('CanNang')
            ->orderByDesc(Schema::hasColumn('chisosuckhoe', 'Ngay') ? 'Ngay' : 'ID')
            ->orderByDesc('ID')
            ->limit(2)
            ->get(['ID', 'CanNang', Schema::hasColumn('chisosuckhoe', 'Ngay') ? 'Ngay' : DB::raw('NULL as Ngay')]);
        if ($rows->count() < 2) {
            return [];
        }

        $latest = $rows->first();
        $previous = $rows->last();
        $delta = round((float) $latest->CanNang - (float) $previous->CanNang, 1);
        if (abs($delta) < 3) {
            return [];
        }

        return array_filter([
            $this->recordEvent($userId, 'weight_fast_change', [
                'title' => 'Can nang thay doi nhanh hon binh thuong',
                'message' => 'Can nang thay doi ' . ($delta > 0 ? '+' : '') . "{$delta} kg giua 2 lan ghi nhan gan nhat. Hay kiem tra lai so lieu va theo doi them.",
                'action' => 'Neu thay doi can nang khong chu y, kem met moi, phu, kho tho hoac trieu chung bat thuong, hay tham khao nhan vien y te.',
                'source_table' => 'chisosuckhoe',
                'source_id' => $sourceId ?: (int) $latest->ID,
                'metadata' => ['delta_kg' => $delta, 'latest_weight' => (float) $latest->CanNang, 'previous_weight' => (float) $previous->CanNang],
                'severity_override' => abs($delta) >= 5 ? 'high' : 'medium',
                'score_override' => abs($delta) >= 5 ? 70 : 55,
            ]),
        ]);
    }

    private function evaluatePositiveStreak(int $userId, string $date): array
    {
        $waterGoal = $this->goalValue($userId, self::WATER_TYPES) ?: $this->estimatedWaterGoal($userId);
        $activityGoal = $this->goalValue($userId, self::ACTIVITY_TYPES) ?: 30;
        $success = 0;
        foreach ($this->dateWindow($date, 5) as $day) {
            if ($this->dailyWaterTotal($userId, $day) >= $waterGoal && $this->dailyActivityMinutes($userId, $day) >= $activityGoal) {
                $success++;
            }
        }

        if ($success < 5) {
            return [];
        }

        return array_filter([
            $this->recordEvent($userId, 'health_positive_streak', [
                'title' => 'Tuyet voi! Ban dang duy tri thoi quen rat tot',
                'message' => 'Ban da hoan thanh muc tieu nuoc va van dong 5 ngay lien tiep. Tiep tuc giu nhip nay nhe!',
                'action' => 'Ghi nhan thanh tich va tiep tuc duy tri thoi quen.',
                'source_table' => null,
                'source_id' => null,
                'metadata' => ['date' => $date, 'streak_days' => $success],
            ]),
        ]);
    }

    private function recordEvent(int $userId, string $ruleCode, array $payload): ?array
    {
        if (!Schema::hasTable('risk_events') || !Schema::hasTable('risk_rules')) {
            return null;
        }

        $rule = DB::table('risk_rules')->where('Code', $ruleCode)->where('Enabled', 1)->first();
        if (!$rule) {
            return null;
        }

        $severity = $payload['severity_override'] ?? $rule->Severity;
        $score = min(100, max(0, (int) ($payload['score_override'] ?? $rule->Score) + (int) ($payload['score_delta'] ?? 0)));
        $coolingMinutes = $severity === 'high'
            ? min((int) $rule->CoolingMinutes, 60)
            : (int) $rule->CoolingMinutes;
        $now = now('Asia/Ho_Chi_Minh');

        $recent = DB::table('risk_events')
            ->where('NguoiDungID', $userId)
            ->where('RuleCode', $ruleCode)
            ->where('Status', 'open')
            ->where('LastDetectedAt', '>=', $now->copy()->subMinutes($coolingMinutes))
            ->orderByDesc('ID')
            ->first();

        if ($recent) {
            DB::table('risk_events')->where('ID', $recent->ID)->update([
                'RiskScore' => max((int) $recent->RiskScore, $score),
                'Severity' => $this->maxSeverity((string) $recent->Severity, $severity),
                'LastDetectedAt' => $now,
                'OccurrenceCount' => ((int) $recent->OccurrenceCount) + 1,
                'Metadata' => json_encode($payload['metadata'] ?? [], JSON_UNESCAPED_UNICODE),
                'updated_at' => $now,
            ]);
            return ['created' => false, 'id' => (int) $recent->ID, 'cooled' => true, 'rule' => $ruleCode];
        }

        $visibleToAdmin = (bool) $rule->AdminVisible || in_array($severity, ['medium', 'high'], true);
        $id = DB::table('risk_events')->insertGetId([
            'NguoiDungID' => $userId,
            'RuleID' => $rule->ID,
            'RuleCode' => $ruleCode,
            'Category' => $rule->Category,
            'Severity' => $severity,
            'RiskScore' => $score,
            'Title' => $payload['title'],
            'Message' => $payload['message'],
            'Action' => $payload['action'] ?? null,
            'Status' => 'open',
            'NotifyUser' => 1,
            'VisibleToAdmin' => $visibleToAdmin ? 1 : 0,
            'SourceTable' => $payload['source_table'] ?? null,
            'SourceID' => $payload['source_id'] ?? null,
            'Metadata' => json_encode($payload['metadata'] ?? [], JSON_UNESCAPED_UNICODE),
            'FirstDetectedAt' => $now,
            'LastDetectedAt' => $now,
            'OccurrenceCount' => 1,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $this->notifyUser($userId, $severity, (string) $payload['title'], (string) $payload['message']);
        return ['created' => true, 'id' => (int) $id, 'cooled' => false, 'rule' => $ruleCode, 'severity' => $severity, 'score' => $score];
    }

    private function notifyUser(int $userId, string $severity, string $title, string $message): void
    {
        if (!Schema::hasTable('thongbao')) {
            return;
        }

        $prefix = match ($severity) {
            'high' => 'CANH BAO QUAN TRONG: ',
            'medium' => 'Can chu y: ',
            default => '',
        };

        DB::table('thongbao')->insert([
            'NguoiDungID' => $userId,
            'LoaiThongBao' => 'Risk' . ucfirst($severity),
            'ThoiGian' => now('Asia/Ho_Chi_Minh'),
            'NoiDung' => $prefix . $title . '. ' . $message,
            'TrangThaiGui' => 'DaGui',
            'DaDoc' => 0,
        ]);
    }

    private function medicationRows(int $userId, string $date)
    {
        $query = DB::table('lichdungthuoc as l')->where('l.NguoiDungID', $userId)->whereDate('l.ThoiGian', $date);
        if (Schema::hasTable('thuoc')) {
            $query->leftJoin('thuoc as t', 't.ID', '=', 'l.ThuocID');
        }
        return $query->get([
            'l.ID',
            'l.NguoiDungID',
            'l.ThuocID',
            'l.ThoiGian',
            'l.TrangThai',
            Schema::hasColumn('lichdungthuoc', 'ThoiGianUongThucTe') ? 'l.ThoiGianUongThucTe' : DB::raw('NULL as ThoiGianUongThucTe'),
            Schema::hasColumn('lichdungthuoc', 'TanSuat') ? 'l.TanSuat' : DB::raw('NULL as TanSuat'),
            Schema::hasColumn('lichdungthuoc', 'LoaiThuoc') ? 'l.LoaiThuoc' : DB::raw('NULL as LoaiThuoc'),
            Schema::hasTable('thuoc') ? 't.TenThuoc' : DB::raw('NULL as TenThuoc'),
            Schema::hasTable('thuoc') && Schema::hasColumn('thuoc', 'SoLanMoiNgay') ? 't.SoLanMoiNgay' : DB::raw('NULL as SoLanMoiNgay'),
            Schema::hasTable('thuoc') && Schema::hasColumn('thuoc', 'NhomThuoc') ? 't.NhomThuoc' : DB::raw('NULL as NhomThuoc'),
        ]);
    }

    private function dailyWaterTotal(int $userId, string $date): int
    {
        if (!Schema::hasTable('theodoinuoc')) {
            return 0;
        }
        return (int) DB::table('theodoinuoc')->where('NguoiDungID', $userId)->whereDate('Ngay', $date)->sum('LuongNuoc');
    }

    private function dailyActivityMinutes(int $userId, string $date): int
    {
        if (Schema::hasTable('activity_logs')) {
            return (int) DB::table('activity_logs')->where('NguoiDungID', $userId)->whereDate('NgayHoatDong', $date)->sum('ThoiLuongPhut');
        }
        if (Schema::hasTable('lichhoatdong')) {
            return (int) DB::table('lichhoatdong')
                ->where('NguoiDungID', $userId)
                ->whereDate('ThoiGianBatDau', $date)
                ->selectRaw('SUM(TIMESTAMPDIFF(MINUTE, ThoiGianBatDau, ThoiGianKetThuc)) as total')
                ->value('total');
        }
        return 0;
    }

    private function averageActivityMinutes(int $userId, string $date, int $days): float
    {
        $values = [];
        foreach ($this->dateWindow(Carbon::parse($date)->subDay()->toDateString(), $days) as $day) {
            $values[] = $this->dailyActivityMinutes($userId, $day);
        }
        $values = array_filter($values, fn ($value) => $value > 0);
        return empty($values) ? 0 : round(array_sum($values) / count($values), 1);
    }

    private function dailyCalories(int $userId, string $date): int
    {
        if (!Schema::hasTable('buaan')) {
            return 0;
        }
        return (int) DB::table('buaan')->where('NguoiDungID', $userId)->whereDate('Ngay', $date)->sum('TongCalories');
    }

    private function dateWindow(string $endDate, int $days): array
    {
        $end = Carbon::parse($endDate, 'Asia/Ho_Chi_Minh');
        $start = $end->copy()->subDays($days - 1);
        $out = [];
        while ($start->lte($end)) {
            $out[] = $start->toDateString();
            $start->addDay();
        }
        return $out;
    }

    private function goalValue(int $userId, array $types): ?float
    {
        if (Schema::hasTable('muctieunguoidung')) {
            $goal = DB::table('muctieunguoidung')->where('NguoiDungID', $userId)->whereIn('Loai', $types)->latest('ID')->value('GiaTri');
            if ($goal) return (float) $goal;
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
                ->latest('ID')
                ->value('GiaTriMucTieu');
            if ($goal) return (float) $goal;
        }
        return null;
    }

    private function estimatedWaterGoal(int $userId): int
    {
        $weight = Schema::hasTable('hosonguoidung')
            ? (float) DB::table('hosonguoidung')->where('NguoiDungID', $userId)->value('CanNang')
            : 0;
        return $weight > 0 ? (int) min(max($weight * 35, 1500), 3000) : 1500;
    }

    private function isHighRiskMedicationGroup(string $group): bool
    {
        $text = mb_strtolower($group);
        foreach (['khang sinh', 'tim', 'huyet ap', 'tieu duong', 'ho hap', 'insulin', 'chong dong'] as $keyword) {
            if (str_contains($text, $keyword)) {
                return true;
            }
        }
        return false;
    }

    private function maxSeverity(string $a, string $b): string
    {
        $rank = ['low' => 1, 'medium' => 2, 'high' => 3];
        return ($rank[$b] ?? 0) > ($rank[$a] ?? 0) ? $b : $a;
    }
}
