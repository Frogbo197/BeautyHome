<?php

namespace App\Service;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class HealthScoreService
{
    public function calculateForUser(int $userId): array
    {
        $profile = DB::table('hosonguoidung')
            ->where('NguoiDungID', $userId)
            ->first();

        $health = DB::table('hososuckhoe')
            ->where('NguoiDungID', $userId)
            ->first();

        $latestIndex = DB::table('chisosuckhoe')
            ->where('NguoiDungID', $userId)
            ->latest('ID')
            ->first();

        $preferences = DB::table('sothichnguoidung')
            ->where('NguoiDung', $userId)
            ->latest('ID')
            ->first();

        $weightKg = $latestIndex?->CanNang ?? $profile?->CanNang;
        $heightCm = $profile?->ChieuCao;
        $bmi = $latestIndex?->BMI;

        if (!$bmi && $weightKg && $heightCm && $heightCm > 0) {
            $heightM = $heightCm / 100;
            $bmi = round($weightKg / ($heightM * $heightM), 1);
        }

        $age = $this->ageFromBirthDate($profile?->NgaySinh);
        $sex = $this->normalizeSex($profile?->GioiTinh);

        $components = [];
        $components['bmi'] = $this->scoreBmi($bmi);
        $components['blood_pressure'] = $this->scoreBloodPressure($latestIndex?->HuyetAp);
        $components['heart_rate'] = $this->scoreHeartRate($latestIndex?->NhipTim);
        $components['activity'] = $this->scoreActivity($userId, $preferences?->MucDoVanDong);
        $components['water'] = $this->scoreWater($userId, $weightKg);
        $components['nutrition'] = $this->scoreNutrition($userId, $age, $sex, $weightKg, $heightCm);
        $components['medication'] = $this->scoreMedication($userId);
        $components['conditions'] = $this->scoreConditions($health?->BenhNen, $health?->TheTrang);

        $score = $this->weightedAverage($components);
        $missing = [];

        foreach ($components as $key => $component) {
            if ($component['score'] === null) {
                $missing[] = $key;
            }
        }

        $status = $score === null
            ? 'Chua du du lieu'
            : $this->statusForScore($score);

        $advice = $this->buildAdvice($components, $score);

        return [
            'success' => true,
            'score' => $score,
            'status' => $status,
            'advice' => $advice,
            'bmi' => $bmi,
            'bmi_category' => $this->bmiCategory($bmi),
            'components' => $components,
            'missing_data' => $missing,
            'summary' => $this->summary($score, $status, $missing),
            'input_snapshot' => [
                'profile' => $profile,
                'health' => $health,
                'latest_index' => $latestIndex,
                'preferences' => $preferences,
            ],
        ];
    }

    private function weightedAverage(array $components): ?int
    {
        $totalWeight = 0;
        $weightedScore = 0;

        foreach ($components as $component) {
            if ($component['score'] === null) {
                continue;
            }

            $weight = $component['weight'];
            $totalWeight += $weight;
            $weightedScore += $component['score'] * $weight;
        }

        if ($totalWeight <= 0) {
            return null;
        }

        return (int) round($weightedScore / $totalWeight);
    }

    private function scoreBmi(?float $bmi): array
    {
        if (!$bmi || $bmi <= 0) {
            return $this->unknown(25, 'Chua co chieu cao/can nang de tinh BMI');
        }

        if ($bmi < 16) {
            return $this->known(25, 35, 'BMI rat thap', 'WHO adult BMI: underweight <18.5');
        }

        if ($bmi < 18.5) {
            return $this->known(25, 55, 'BMI thap', 'WHO adult BMI: underweight <18.5');
        }

        if ($bmi < 25) {
            return $this->known(25, 100, 'BMI trong khoang khoe manh', 'WHO adult BMI: healthy 18.5-24.9');
        }

        if ($bmi < 30) {
            return $this->known(25, 70, 'BMI thua can', 'WHO adult BMI: overweight 25-29.9');
        }

        if ($bmi < 35) {
            return $this->known(25, 45, 'BMI beo phi', 'WHO adult BMI: obesity >=30');
        }

        return $this->known(25, 30, 'BMI beo phi muc cao', 'WHO adult BMI: obesity >=30');
    }

    private function scoreBloodPressure(?string $bloodPressure): array
    {
        $parsed = $this->parseBloodPressure($bloodPressure);
        if (!$parsed) {
            return $this->unknown(15, 'Chua co huyet ap');
        }

        [$sys, $dia] = $parsed;

        if ($sys > 180 || $dia > 120) {
            return $this->known(15, 15, 'Huyet ap rat cao, can lien he nhan vien y te', 'AHA crisis threshold');
        }

        if ($sys >= 140 || $dia >= 90) {
            return $this->known(15, 40, 'Huyet ap cao giai doan 2', 'AHA stage 2 hypertension');
        }

        if (($sys >= 130 && $sys <= 139) || ($dia >= 80 && $dia <= 89)) {
            return $this->known(15, 65, 'Huyet ap cao giai doan 1', 'AHA stage 1 hypertension');
        }

        if ($sys >= 120 && $sys <= 129 && $dia < 80) {
            return $this->known(15, 85, 'Huyet ap tang', 'AHA elevated blood pressure');
        }

        if ($sys < 120 && $dia < 80) {
            return $this->known(15, 100, 'Huyet ap binh thuong', 'AHA normal blood pressure');
        }

        return $this->known(15, 70, 'Huyet ap can theo doi them', 'Rule-based blood pressure fallback');
    }

    private function scoreHeartRate(?int $heartRate): array
    {
        if (!$heartRate || $heartRate <= 0) {
            return $this->unknown(10, 'Chua co nhip tim');
        }

        if ($heartRate >= 60 && $heartRate <= 100) {
            return $this->known(10, 100, 'Nhip tim nghi nam trong khoang thuong gap', 'Rule-based adult resting heart rate');
        }

        if (($heartRate >= 50 && $heartRate < 60) || ($heartRate > 100 && $heartRate <= 110)) {
            return $this->known(10, 75, 'Nhip tim hoi lech khoang tham chieu', 'Rule-based adult resting heart rate');
        }

        if ($heartRate > 110 && $heartRate <= 120) {
            return $this->known(10, 55, 'Nhip tim cao, nen theo doi them', 'Rule-based adult resting heart rate');
        }

        return $this->known(10, 40, 'Nhip tim bat thuong, nen tham van y te neu lap lai', 'Rule-based adult resting heart rate');
    }

    private function scoreActivity(int $userId, ?string $activityPreference): array
    {
        $weekStart = Carbon::now('Asia/Ho_Chi_Minh')->subDays(6)->startOfDay();

        $records = DB::table('lichhoatdong as l')
            ->leftJoin('chitiethoatdong as c', 'c.LichHoatDongID', '=', 'l.ID')
            ->where('l.NguoiDungID', $userId)
            ->where('l.ThoiGianBatDau', '>=', $weekStart)
            ->where(function ($query) {
                $query->whereNull('l.TrangThai')
                    ->orWhereIn('l.TrangThai', ['HoanThanh', 'DaHoanThanh', 'completed']);
            })
            ->get([
                'l.ThoiGianBatDau',
                'l.ThoiGianKetThuc',
                'c.CaloDot',
            ]);

        $minutes = 0;
        foreach ($records as $record) {
            if ($record->ThoiGianBatDau && $record->ThoiGianKetThuc) {
                $start = Carbon::parse($record->ThoiGianBatDau);
                $end = Carbon::parse($record->ThoiGianKetThuc);
                $diff = max(0, $start->diffInMinutes($end));
                $minutes += $diff;
            }
        }

        if ($minutes > 0) {
            if ($minutes >= 300) {
                return $this->known(15, 100, "{$minutes} phut van dong/7 ngay", 'WHO adult activity: 150-300 min/week moderate');
            }
            if ($minutes >= 150) {
                return $this->known(15, 85, "{$minutes} phut van dong/7 ngay", 'WHO adult activity: at least 150 min/week moderate');
            }
            if ($minutes >= 75) {
                return $this->known(15, 60, "{$minutes} phut van dong/7 ngay", 'Below WHO adult weekly target');
            }
            return $this->known(15, 35, "{$minutes} phut van dong/7 ngay", 'Below WHO adult weekly target');
        }

        $mapped = $this->scoreActivityPreference($activityPreference);
        if ($mapped !== null) {
            return $mapped;
        }

        return $this->unknown(15, 'Chua co du lieu van dong');
    }

    private function scoreWater(int $userId, ?float $weightKg): array
    {
        $weekStart = Carbon::now('Asia/Ho_Chi_Minh')->subDays(6)->toDateString();
        $avgWater = DB::table('theodoinuoc')
            ->where('NguoiDungID', $userId)
            ->whereDate('Ngay', '>=', $weekStart)
            ->avg('LuongNuoc');

        if ($avgWater === null) {
            return $this->unknown(10, 'Chua co du lieu uong nuoc');
        }

        $target = $weightKg && $weightKg > 0
            ? min(max($weightKg * 35, 1500), 3000)
            : 2000;

        $ratio = $avgWater / max($target, 1);

        if ($ratio >= 0.9 && $ratio <= 1.4) {
            return $this->known(10, 100, 'Luong nuoc trung binh phu hop muc tieu ca nhan', 'Rule-based hydration target 30-35 ml/kg/day');
        }

        if ($ratio >= 0.7) {
            return $this->known(10, 75, 'Luong nuoc hoi thap so voi muc tieu', 'Rule-based hydration target 30-35 ml/kg/day');
        }

        if ($ratio > 1.4) {
            return $this->known(10, 70, 'Luong nuoc cao hon muc tieu, can ca nhan hoa theo benh ly', 'Rule-based hydration target 30-35 ml/kg/day');
        }

        return $this->known(10, 45, 'Luong nuoc thap', 'Rule-based hydration target 30-35 ml/kg/day');
    }

    private function scoreNutrition(
        int $userId,
        ?int $age,
        ?string $sex,
        ?float $weightKg,
        ?float $heightCm
    ): array {
        $today = Carbon::now('Asia/Ho_Chi_Minh')->toDateString();

        $calories = DB::table('buaan as b')
            ->leftJoin('chitietbuaan as c', 'c.BuaAnID', '=', 'b.ID')
            ->where('b.NguoiDungID', $userId)
            ->whereDate('b.Ngay', $today)
            ->sum('c.TongCalo');

        if (!$calories || !$age || !$sex || !$weightKg || !$heightCm) {
            return $this->unknown(10, 'Chua du du lieu an uong/calorie muc tieu');
        }

        $target = $this->estimatedCalories($age, $sex, $weightKg, $heightCm);
        $deviation = abs($calories - $target) / max($target, 1);

        if ($deviation <= 0.15) {
            return $this->known(10, 100, 'Nang luong nap vao gan muc uoc tinh', 'Rule-based Mifflin-St Jeor estimate');
        }

        if ($deviation <= 0.30) {
            return $this->known(10, 75, 'Nang luong nap vao lech nhe so voi muc uoc tinh', 'Rule-based Mifflin-St Jeor estimate');
        }

        return $this->known(10, 45, 'Nang luong nap vao lech nhieu so voi muc uoc tinh', 'Rule-based Mifflin-St Jeor estimate');
    }

    private function scoreMedication(int $userId): array
    {
        $total = DB::table('lichdungthuoc')
            ->where('NguoiDungID', $userId)
            ->count();

        if ($total === 0) {
            return $this->unknown(5, 'Khong co lich thuoc de cham diem tuan thu');
        }

        $taken = DB::table('lichdungthuoc')
            ->where('NguoiDungID', $userId)
            ->whereIn('TrangThai', ['DaUong', 'Da uong', 'done', 'completed'])
            ->count();

        $ratio = $taken / max($total, 1);

        if ($ratio >= 0.9) {
            return $this->known(5, 100, 'Tuan thu thuoc tot', 'Rule-based medication adherence');
        }

        if ($ratio >= 0.7) {
            return $this->known(5, 75, 'Tuan thu thuoc kha', 'Rule-based medication adherence');
        }

        return $this->known(5, 45, 'Tuan thu thuoc thap', 'Rule-based medication adherence');
    }

    private function scoreConditions(?string $conditions, ?string $bodyStatus): array
    {
        $hasCondition = $this->hasMeaningfulCondition($conditions);
        $bodyScore = $this->scoreBodyStatus($bodyStatus);

        if (!$hasCondition && $bodyScore === null) {
            return $this->unknown(15, 'Chua co benh nen/the trang');
        }

        $score = $bodyScore ?? 100;
        if ($hasCondition) {
            $score -= 20;
        }

        return $this->known(
            15,
            max(20, $score),
            $hasCondition ? 'Co benh nen can theo doi' : 'Chua ghi nhan benh nen dang ke',
            'Rule-based onboarding risk flags'
        );
    }

    private function scoreActivityPreference(?string $activityPreference): ?array
    {
        $value = $this->normalizeText($activityPreference);
        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'rat') || str_contains($value, 'cao') || str_contains($value, 'active')) {
            return $this->known(15, 90, 'Muc do van dong tu khai cao', 'Onboarding self-report fallback');
        }

        if (str_contains($value, 'trung') || str_contains($value, 'moderate')) {
            return $this->known(15, 75, 'Muc do van dong tu khai trung binh', 'Onboarding self-report fallback');
        }

        if (str_contains($value, 'nhe') || str_contains($value, 'light')) {
            return $this->known(15, 60, 'Muc do van dong tu khai nhe', 'Onboarding self-report fallback');
        }

        if (str_contains($value, 'it') || str_contains($value, 'sedentary') || str_contains($value, 'khong')) {
            return $this->known(15, 35, 'Muc do van dong tu khai thap', 'Onboarding self-report fallback');
        }

        return null;
    }

    private function scoreBodyStatus(?string $bodyStatus): ?int
    {
        $value = $this->normalizeText($bodyStatus);
        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'binh') || str_contains($value, 'normal')) {
            return 100;
        }

        if (str_contains($value, 'thua') || str_contains($value, 'overweight')) {
            return 75;
        }

        if (str_contains($value, 'beo') || str_contains($value, 'obesity')) {
            return 45;
        }

        if (str_contains($value, 'gay') || str_contains($value, 'under')) {
            return 60;
        }

        if (str_contains($value, 'yeu')) {
            return 55;
        }

        return null;
    }

    private function estimatedCalories(int $age, string $sex, float $weightKg, float $heightCm): float
    {
        $bmr = $sex === 'female'
            ? (10 * $weightKg) + (6.25 * $heightCm) - (5 * $age) - 161
            : (10 * $weightKg) + (6.25 * $heightCm) - (5 * $age) + 5;

        return $bmr * 1.3;
    }

    private function parseBloodPressure(?string $bloodPressure): ?array
    {
        if (!$bloodPressure) {
            return null;
        }

        if (!preg_match('/(\d{2,3})\s*\/\s*(\d{2,3})/', $bloodPressure, $matches)) {
            return null;
        }

        return [(int) $matches[1], (int) $matches[2]];
    }

    private function ageFromBirthDate(?string $birthDate): ?int
    {
        if (!$birthDate) {
            return null;
        }

        try {
            return Carbon::parse($birthDate)->age;
        } catch (\Throwable) {
            return null;
        }
    }

    private function normalizeSex(?string $sex): ?string
    {
        $value = $this->normalizeText($sex);
        if ($value === '') {
            return null;
        }

        if (str_contains($value, 'nu') || str_contains($value, 'female')) {
            return 'female';
        }

        return 'male';
    }

    private function hasMeaningfulCondition(?string $conditions): bool
    {
        $value = $this->normalizeText($conditions);
        if ($value === '') {
            return false;
        }

        return !in_array($value, ['khong', 'khong co', 'none', 'no', 'binh thuong'], true);
    }

    private function normalizeText(?string $value): string
    {
        $value = mb_strtolower(trim((string) $value), 'UTF-8');
        $value = strtr($value, [
            'á' => 'a', 'à' => 'a', 'ả' => 'a', 'ã' => 'a', 'ạ' => 'a',
            'ă' => 'a', 'ắ' => 'a', 'ằ' => 'a', 'ẳ' => 'a', 'ẵ' => 'a', 'ặ' => 'a',
            'â' => 'a', 'ấ' => 'a', 'ầ' => 'a', 'ẩ' => 'a', 'ẫ' => 'a', 'ậ' => 'a',
            'đ' => 'd',
            'é' => 'e', 'è' => 'e', 'ẻ' => 'e', 'ẽ' => 'e', 'ẹ' => 'e',
            'ê' => 'e', 'ế' => 'e', 'ề' => 'e', 'ể' => 'e', 'ễ' => 'e', 'ệ' => 'e',
            'í' => 'i', 'ì' => 'i', 'ỉ' => 'i', 'ĩ' => 'i', 'ị' => 'i',
            'ó' => 'o', 'ò' => 'o', 'ỏ' => 'o', 'õ' => 'o', 'ọ' => 'o',
            'ô' => 'o', 'ố' => 'o', 'ồ' => 'o', 'ổ' => 'o', 'ỗ' => 'o', 'ộ' => 'o',
            'ơ' => 'o', 'ớ' => 'o', 'ờ' => 'o', 'ở' => 'o', 'ỡ' => 'o', 'ợ' => 'o',
            'ú' => 'u', 'ù' => 'u', 'ủ' => 'u', 'ũ' => 'u', 'ụ' => 'u',
            'ư' => 'u', 'ứ' => 'u', 'ừ' => 'u', 'ử' => 'u', 'ữ' => 'u', 'ự' => 'u',
            'ý' => 'y', 'ỳ' => 'y', 'ỷ' => 'y', 'ỹ' => 'y', 'ỵ' => 'y',
        ]);
        $from = ['á','à','ả','ã','ạ','ă','ắ','ằ','ẳ','ẵ','ặ','â','ấ','ầ','ẩ','ẫ','ậ','đ','é','è','ẻ','ẽ','ẹ','ê','ế','ề','ể','ễ','ệ','í','ì','ỉ','ĩ','ị','ó','ò','ỏ','õ','ọ','ô','ố','ồ','ổ','ỗ','ộ','ơ','ớ','ờ','ở','ỡ','ợ','ú','ù','ủ','ũ','ụ','ư','ứ','ừ','ử','ữ','ự','ý','ỳ','ỷ','ỹ','ỵ'];
        $to = ['a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','a','d','e','e','e','e','e','e','e','e','e','e','e','i','i','i','i','i','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','o','u','u','u','u','u','u','u','u','u','u','u','y','y','y','y','y'];

        return str_replace($from, $to, $value);
    }

    private function bmiCategory(?float $bmi): ?string
    {
        if (!$bmi || $bmi <= 0) {
            return null;
        }

        if ($bmi < 18.5) {
            return 'Underweight';
        }
        if ($bmi < 25) {
            return 'Healthy';
        }
        if ($bmi < 30) {
            return 'Overweight';
        }
        return 'Obesity';
    }

    private function statusForScore(int $score): string
    {
        if ($score >= 80) {
            return 'Tot';
        }
        if ($score >= 60) {
            return 'Trung binh';
        }
        if ($score >= 40) {
            return 'Can cai thien';
        }
        return 'Can chu y';
    }

    private function buildAdvice(array $components, ?int $score): array
    {
        if ($score === null) {
            return ['Hay cap nhat chieu cao, can nang va mot vai chi so co ban de tinh diem chinh xac hon.'];
        }

        $advice = [];
        foreach ($components as $key => $component) {
            if ($component['score'] !== null && $component['score'] < 70) {
                $advice[] = $component['message'];
            }
        }

        if (empty($advice)) {
            $advice[] = 'Tiep tuc duy tri cac thoi quen hien tai va cap nhat du lieu hang ngay.';
        }

        return array_values(array_unique($advice));
    }

    private function summary(?int $score, string $status, array $missing): string
    {
        if ($score === null) {
            return 'Chua du du lieu de tinh health score.';
        }

        $suffix = empty($missing)
            ? ''
            : ' Mot so muc chua co du lieu: ' . implode(', ', $missing) . '.';

        return "Health score {$score}/100 - {$status}.{$suffix}";
    }

    private function known(int $weight, int $score, string $message, string $source): array
    {
        return [
            'score' => $score,
            'weight' => $weight,
            'status' => 'known',
            'message' => $message,
            'source' => $source,
        ];
    }

    private function unknown(int $weight, string $message): array
    {
        return [
            'score' => null,
            'weight' => $weight,
            'status' => 'unknown',
            'message' => $message,
            'source' => null,
        ];
    }
}
