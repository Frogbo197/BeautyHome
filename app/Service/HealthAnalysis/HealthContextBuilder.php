<?php

namespace App\Service\HealthAnalysis;

use App\DTO\HealthContextDTO;
use App\Models\ActivityLog;
use App\Models\BuaAn;
use App\Models\ChiSoSucKhoe;
use App\Models\HoSoNguoiDung;
use App\Models\HoSoSucKhoe;
use App\Models\SoThichNguoiDung;
use App\Models\TaiKhoan;

class HealthContextBuilder
{
    public function __construct(
        private readonly HealthMetricCalculator $metrics,
        private readonly FoodPreferenceService $foodPreferences,
    ) {
    }

    public function build(int $userId): HealthContextDTO
    {
        $user = TaiKhoan::findOrFail($userId);
        $profile = HoSoNguoiDung::where('NguoiDungID', $userId)->first();
        $health = HoSoSucKhoe::where('NguoiDungID', $userId)->first();
        $latestIndex = ChiSoSucKhoe::where('NguoiDungID', $userId)->latest('ID')->first();
        $preferences = SoThichNguoiDung::where('NguoiDung', $userId)->first();
        $foodPreferences = $this->foodPreferences->forUser($userId);

        $heightCm = (float) ($profile?->ChieuCao ?? 0);
        $weightKg = (float) ($profile?->CanNang ?? 0);
        $bmi = $this->metrics->bmi($latestIndex?->BMI, $weightKg, $heightCm);
        $age = $this->metrics->age($profile?->NgaySinh, 20);
        $sex = $this->metrics->sex($profile?->GioiTinh);

        [$meals, $mealSummary, $totalCalories, $totalProtein, $totalCarb, $totalFat] = $this->mealContext($userId);
        [$activities, $activitySummary, $totalActivityCalories, $activityMinutes, $totalSteps] = $this->activityContext($userId);
        $activityLevel = $this->metrics->activityLevel($totalActivityCalories);

        return new HealthContextDTO(
            userId: $userId,
            user: $user,
            profile: $profile,
            health: $health,
            latestIndex: $latestIndex,
            preferences: $preferences,
            foodPreferences: $foodPreferences,
            meals: $meals,
            activities: $activities,
            heightCm: $heightCm,
            weightKg: $weightKg,
            bmi: $bmi,
            age: $age,
            sex: $sex,
            mealSummary: $mealSummary,
            totalCalories: $totalCalories,
            totalProtein: $totalProtein,
            totalCarb: $totalCarb,
            totalFat: $totalFat,
            activitySummary: $activitySummary,
            totalActivityCalories: $totalActivityCalories,
            activityMinutes: $activityMinutes,
            totalSteps: $totalSteps,
            activityLevel: $activityLevel,
        );
    }

    private function mealContext(int $userId): array
    {
        $meals = BuaAn::with('chiTiet.thucPham')
            ->where('NguoiDungID', $userId)
            ->latest('ID')
            ->limit(5)
            ->get();

        $mealSummary = '';
        $totalCalories = 0.0;
        $totalProtein = 0.0;
        $totalCarb = 0.0;
        $totalFat = 0.0;

        foreach ($meals as $meal) {
            $foodName = trim($meal->TenMonAn ?? '');
            if ($foodName === '') {
                $foodName = 'Khong ro';
            }

            $detail = $meal->chiTiet->first();
            $food = $detail?->thucPham;
            $calories = (float) ($detail?->TongCalo ?? $food?->Calo ?? 0);
            $protein = (float) ($detail?->TongProtein ?? $food?->Protein ?? 0);
            $carb = (float) ($detail?->TongCarb ?? $food?->Carb ?? 0);
            $fat = (float) ($detail?->TongFat ?? $food?->ChatBeo ?? 0);

            $mealSummary .= "- {$foodName} ({$calories} calo)\n";
            $totalCalories += $calories;
            $totalProtein += $protein;
            $totalCarb += $carb;
            $totalFat += $fat;
        }

        if ($mealSummary === '') {
            // Fallback cu: giu hanh vi khi chua co log bua an.
            $mealSummary = 'Chua co du lieu bua an hom nay.';
        }

        return [$meals, $mealSummary, $totalCalories, $totalProtein, $totalCarb, $totalFat];
    }

    private function activityContext(int $userId): array
    {
        $activities = ActivityLog::where('NguoiDungID', $userId)
            ->latest('ID')
            ->limit(5)
            ->get();

        $activitySummary = '';
        $totalActivityCalories = 0.0;
        $activityMinutes = 0;
        $totalSteps = 0;

        foreach ($activities as $activity) {
            $activityName = $activity->TenHoatDong ?? 'Khac';
            $activityCalories = (float) ($activity->CaloriesBurned ?? 0);
            $duration = (int) ($activity->ThoiLuongPhut ?? 0);
            $steps = (int) ($activity->Steps ?? 0);

            $activitySummary .= "- {$activityName}: {$activityCalories} calo\n";
            $activityMinutes += $duration;
            $totalSteps += $steps;
            $totalActivityCalories += $activityCalories;
        }

        if ($activitySummary === '') {
            // Fallback cu: giu hanh vi khi chua co hoat dong.
            $activitySummary = 'Chua co hoat dong nao duoc ghi nhan.';
        }

        return [$activities, $activitySummary, $totalActivityCalories, $activityMinutes, $totalSteps];
    }
}
