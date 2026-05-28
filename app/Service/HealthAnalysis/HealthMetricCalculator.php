<?php

namespace App\Service\HealthAnalysis;

use Carbon\Carbon;

class HealthMetricCalculator
{
    public function bmi(?float $latestBmi, float $weightKg, float $heightCm): float
    {
        if ($latestBmi !== null && $latestBmi > 0) {
            return (float) $latestBmi;
        }

        if ($weightKg <= 0) {
            return 0.0;
        }

        $heightM = $heightCm > 0 ? $heightCm / 100 : 1.7;

        return round($weightKg / ($heightM * $heightM), 1);
    }

    public function age(?string $birthDate, int $fallback = 20): int
    {
        if (empty($birthDate)) {
            return $fallback;
        }

        try {
            $parsed = Carbon::parse($birthDate)->age;

            return ($parsed > 0 && $parsed < 120) ? $parsed : $fallback;
        } catch (\Throwable) {
            return $fallback;
        }
    }

    public function sex(?string $sex): string
    {
        $gioiTinh = strtolower(trim($sex ?? 'nam'));

        return $gioiTinh === 'nam' ? 'M' : 'F';
    }

    public function activityLevel(float $totalActivityCalories): string
    {
        if ($totalActivityCalories >= 500) {
            return 'active';
        }

        if ($totalActivityCalories < 100) {
            return 'sedentary';
        }

        return 'moderate';
    }

    public function scoreContext(int $score): array
    {
        if ($score >= 80) {
            return ['tot', 'Suc khoe dang rat tot, duy tri nhe!'];
        }

        if ($score >= 60) {
            return ['kha', 'Suc khoe kha on, con co the cai thien them.'];
        }

        if ($score >= 40) {
            return ['trung binh', 'Can chu y hon ve an uong va van dong.'];
        }

        return ['yeu', 'Can dieu chinh loi song som.'];
    }

    public function bmiLabelAndAdvice(float $bmi): array
    {
        if ($bmi === 0.0) {
            return ['chua co du lieu', 'cap nhat chieu cao va can nang nha'];
        }

        if ($bmi < 18.5) {
            return ['thieu can', 'nen an nhieu hon va bo sung dinh duong'];
        }

        if ($bmi <= 24.9) {
            return ['binh thuong', 'BMI dang o muc ly tuong, duy tri tot'];
        }

        if ($bmi <= 29.9) {
            return ['thua can', 'nen tang van dong va kiem soat khau phan an'];
        }

        return ['beo phi', 'nen tham khao chuyen gia dinh duong, an uong hop ly va tang van dong'];
    }

    public function calorieContext(float $totalCalories, float $totalActivityCalories): string
    {
        $balance = $totalCalories - $totalActivityCalories;

        if ($totalCalories === 0.0) {
            return 'chua ghi nhan bua an hom nay';
        }

        if ($totalActivityCalories === 0.0) {
            return "da nap {$totalCalories} calo nhung chua van dong";
        }

        if ($balance > 300) {
            return "dang du {$balance} calo";
        }

        if ($balance < -300) {
            return 'dang thieu hut ' . abs($balance) . ' calo';
        }

        return 'calo kha can bang';
    }
}
