<?php

namespace App\DTO;

use App\Models\ChiSoSucKhoe;
use App\Models\HoSoNguoiDung;
use App\Models\HoSoSucKhoe;
use App\Models\SoThichNguoiDung;
use App\Models\TaiKhoan;
use Illuminate\Support\Collection;

class HealthContextDTO
{
    public function __construct(
        public readonly int $userId,
        public readonly TaiKhoan $user,
        public readonly ?HoSoNguoiDung $profile,
        public readonly ?HoSoSucKhoe $health,
        public readonly ?ChiSoSucKhoe $latestIndex,
        public readonly ?SoThichNguoiDung $preferences,
        public readonly array $foodPreferences,
        public readonly Collection $meals,
        public readonly Collection $activities,
        public readonly float $heightCm,
        public readonly float $weightKg,
        public readonly float $bmi,
        public readonly int $age,
        public readonly string $sex,
        public readonly string $mealSummary,
        public readonly float $totalCalories,
        public readonly float $totalProtein,
        public readonly float $totalCarb,
        public readonly float $totalFat,
        public readonly string $activitySummary,
        public readonly float $totalActivityCalories,
        public readonly int $activityMinutes,
        public readonly int $totalSteps,
        public readonly string $activityLevel,
    ) {
    }

    public function userName(): string
    {
        return (string) ($this->profile?->Ten ?? $this->user->Email);
    }
}
