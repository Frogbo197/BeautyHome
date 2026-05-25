<?php

namespace App\Services\AI;

use App\Models\HoSoNguoiDung;
use App\Models\ChiSoSucKhoe;
use App\Models\DiemSucKhoe;
use App\Models\DailyHealthSummary;
use App\Models\BuaAn;
use App\Models\ActivityLog;
use App\Models\ChiTietBuaAn;
use App\Models\ThucPham;
use Carbon\Carbon;

/**
 * HealthContextService
 *
 * Tập trung toàn bộ logic lấy dữ liệu sức khỏe người dùng.
 * Dùng chung cho AIController và ChatController — không tính lại 2 lần.
 */
class HealthContextService
{
    /**
     * Trả về mảng context đầy đủ cho một user.
     * Đây là single source of truth — cả AI analyze lẫn Chat đều dùng cái này.
     */
    public function build(int $userId): array
    {
        // ── Profile ──────────────────────────────────────────────────────────
        $profile = HoSoNguoiDung::where('NguoiDungID', $userId)->first();
        $chiSo   = ChiSoSucKhoe::where('NguoiDungID', $userId)->latest('ID')->first();

        // ── BMI ──────────────────────────────────────────────────────────────
        $heightCm = $profile?->ChieuCao ?? 0;
        $weight   = $profile?->CanNang  ?? 0;
        $heightM  = $heightCm > 0 ? $heightCm / 100 : 1.7;

        if ($chiSo?->BMI) {
            $bmi = round($chiSo->BMI, 1);
        } elseif ($weight > 0) {
            $bmi = round($weight / ($heightM * $heightM), 1);
        } else {
            $bmi = 0;
        }

        // ── Tuổi ─────────────────────────────────────────────────────────────
        $age = 25;
        if (!empty($profile?->NgaySinh)) {
            try {
                $parsed = Carbon::parse($profile->NgaySinh)->age;
                $age    = ($parsed > 0 && $parsed < 120) ? $parsed : 25;
            } catch (\Throwable) {}
        }

        // ── Giới tính ────────────────────────────────────────────────────────
        $gioiTinh = strtolower(trim($profile?->GioiTinh ?? 'nam'));
        $sex      = $gioiTinh === 'nam' ? 'M' : 'F';

        // ── Calo nạp hôm nay (từ chitietbuaan → thucpham) ───────────────────
        $today = now()->toDateString();

        $todayMeals = BuaAn::where('NguoiDungID', $userId)
            ->whereDate('Ngay', $today)
            ->with(['chiTiet.thucPham'])
            ->get();

        $totalCalories = 0;
        $totalProtein  = 0;
        $totalCarb     = 0;
        $totalFat      = 0;
        $mealLines     = [];

        foreach ($todayMeals as $meal) {
            $mealLabel = $meal->LoaiBuaAn ?? 'Bữa ăn';
            $mealCalo  = 0;
            $foods     = [];

            foreach ($meal->chiTiet ?? [] as $detail) {
                $food = $detail->thucPham;
                if (!$food) continue;

                $qty = $detail->SoLuong ?? 100; // gram
                $ratio = $qty / 100;

                $calo    = round(($food->Calo    ?? 0) * $ratio);
                $protein = round(($food->Protein ?? 0) * $ratio, 1);
                $carb    = round(($food->Carb    ?? 0) * $ratio, 1);
                $fat     = round(($food->ChatBeo ?? 0) * $ratio, 1);

                $totalCalories += $calo;
                $totalProtein  += $protein;
                $totalCarb     += $carb;
                $totalFat      += $fat;
                $mealCalo      += $calo;
                $foods[]        = "{$food->Ten} ({$qty}g, {$calo} kcal)";
            }

            if (!empty($foods)) {
                $mealLines[] = "• {$mealLabel}: " . implode(', ', $foods) . " → {$mealCalo} kcal";
            }
        }

        $mealSummary = !empty($mealLines)
            ? implode("\n", $mealLines)
            : 'Chưa ghi nhận bữa ăn hôm nay.';

        // ── Hoạt động hôm nay ────────────────────────────────────────────────
        $activities = ActivityLog::where('NguoiDungID', $userId)
            ->whereDate('CreatedAt', $today)
            ->get();

        $totalCaloriesBurned = 0;
        $totalMinutes        = 0;
        $totalSteps          = 0;
        $activityLines       = [];

        foreach ($activities as $act) {
            $name     = $act->TenHoatDong    ?? 'Hoạt động';
            $burned   = $act->CaloriesBurned ?? $act->CaloriesDot ?? 0;
            $mins     = $act->ThoiLuongPhut  ?? 0;
            $steps    = $act->Steps          ?? 0;

            $totalCaloriesBurned += $burned;
            $totalMinutes        += $mins;
            $totalSteps          += $steps;
            $activityLines[]      = "• {$name}: {$mins} phút, {$burned} kcal";
        }

        $activitySummary = !empty($activityLines)
            ? implode("\n", $activityLines)
            : 'Chưa ghi nhận hoạt động hôm nay.';

        // ── Cân bằng calo ────────────────────────────────────────────────────
        $balance = $totalCalories - $totalCaloriesBurned;

        // ── Health score gần nhất ────────────────────────────────────────────
        $scoreRecord = DiemSucKhoe::where('NguoiDungID', $userId)->latest('ID')->first();
        $healthScore = $scoreRecord?->Diem ?? 0;

        // ── Daily summary hôm nay (nếu đã có) ────────────────────────────────
        $daily = DailyHealthSummary::where('NguoiDungID', $userId)
            ->whereDate('Ngay', $today)
            ->first();

        // ── BMI label ────────────────────────────────────────────────────────
        [$bmiLabel, $bmiAdvice] = $this->bmiLabelAndAdvice($bmi);

        // ── Calorie context ───────────────────────────────────────────────────
        $calorieContext = $this->calorieContext($totalCalories, $totalCaloriesBurned, $balance);

        // ── Nutrition advice ─────────────────────────────────────────────────
        $nutritionAdvice = $this->nutritionAdvice($totalCalories, $totalProtein, $totalCarb, $totalFat);

        return [
            // User info
            'ten'              => $profile?->Ten ?? 'Bạn',
            'tuoi'             => $age,
            'gioi_tinh'        => $profile?->GioiTinh ?? 'Không rõ',
            'sex'              => $sex,
            'weight'           => $weight,
            'height_cm'        => $heightCm,

            // BMI
            'bmi'              => $bmi,
            'bmi_label'        => $bmiLabel,
            'bmi_advice'       => $bmiAdvice,

            // Calories
            'calo_nap'         => $totalCalories,
            'calo_dot'         => $totalCaloriesBurned,
            'calo_balance'     => $balance,
            'calorie_context'  => $calorieContext,

            // Macros
            'protein'          => round($totalProtein, 1),
            'carb'             => round($totalCarb, 1),
            'fat'              => round($totalFat, 1),

            // Activity
            'activity_minutes' => $totalMinutes,
            'steps'            => $totalSteps,

            // Summaries (human-readable, cho AI đọc)
            'meal_summary'     => $mealSummary,
            'activity_summary' => $activitySummary,

            // Scores & advice
            'health_score'     => $healthScore,
            'nutrition_advice' => $nutritionAdvice,

            // Goal (nếu có)
            'goal'             => $daily?->TrangThaiHoanThanh ?? 'General',
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // HELPERS
    // ─────────────────────────────────────────────────────────────────────────

    private function bmiLabelAndAdvice(float $bmi): array
    {
        if ($bmi === 0.0) {
            return ['chưa có dữ liệu', 'cập nhật chiều cao và cân nặng để theo dõi chính xác hơn'];
        }
        if ($bmi < 18.5) {
            return ['thiếu cân', 'nên ăn nhiều hơn, bổ sung protein và tinh bột lành mạnh'];
        }
        if ($bmi <= 24.9) {
            return ['bình thường', 'BMI đang ở mức lý tưởng, duy trì tốt nhé'];
        }
        if ($bmi <= 29.9) {
            return ['thừa cân', 'nên tăng cardio và kiểm soát khẩu phần ăn'];
        }
        return ['béo phì', 'nên tham khảo chuyên gia dinh dưỡng và tăng cường vận động'];
    }

    private function calorieContext(int $in, float $out, int $balance): string
    {
        if ($in === 0) {
            return 'Chưa ghi nhận bữa ăn hôm nay.';
        }
        if ($out === 0) {
            return "Đã nạp {$in} kcal nhưng chưa có hoạt động thể chất nào.";
        }
        if ($balance > 300) {
            return "Nạp {$in} kcal, đốt {$out} kcal — đang dư {$balance} kcal. Nên vận động thêm.";
        }
        if ($balance < -300) {
            $def = abs($balance);
            return "Nạp {$in} kcal, đốt {$out} kcal — đang thiếu hụt {$def} kcal. Cần ăn thêm.";
        }
        return "Nạp {$in} kcal, đốt {$out} kcal — calo khá cân bằng hôm nay.";
    }

    private function nutritionAdvice(float $calo, float $protein, float $carb, float $fat): string
    {
        $notes = [];

        if ($calo === 0.0) {
            return 'Chưa có dữ liệu bữa ăn để đánh giá.';
        }
        if ($protein < 50) {
            $notes[] = "Protein đang thiếu ({$protein}g / cần ≥50g) — thêm ức gà, trứng, đậu hũ.";
        }
        if ($carb > 300) {
            $notes[] = "Carb hơi cao ({$carb}g) — hạn chế cơm trắng, bánh mì, đồ ngọt.";
        }
        if ($fat > 70) {
            $notes[] = "Chất béo cao ({$fat}g) — hạn chế đồ chiên xào.";
        }
        if ($calo > 2500) {
            $notes[] = "Tổng calo hôm nay khá nhiều ({$calo} kcal).";
        }

        return empty($notes)
            ? "Dinh dưỡng hôm nay khá ổn ({$calo} kcal, protein {$protein}g)."
            : implode(' ', $notes);
    }
}