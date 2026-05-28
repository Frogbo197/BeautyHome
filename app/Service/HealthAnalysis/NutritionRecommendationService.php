<?php

namespace App\Service\HealthAnalysis;

use App\Models\ThucPham;

class NutritionRecommendationService
{
    public const MAX_SUGGESTIONS = 5;

    public function __construct(private readonly FoodPreferenceService $foodPreferenceService)
    {
    }

    public function recommend(array $foodPreferences, float $bmi, float $protein, float $score): array
    {
        $query = ThucPham::where('IsHealthy', 1);

        if ($bmi >= 25) {
            $query->where('Calo', '<=', 350)->orderByDesc('Protein')->orderBy('Calo');
        } elseif ($protein < 50) {
            $query->orderByDesc('Protein')->orderBy('Calo');
        } elseif ($score < 60) {
            $query->orderBy('Calo')->orderByDesc('Protein');
        } else {
            $query->orderByDesc('IsHealthy')->orderBy('Ten');
        }

        $foods = $query->limit(40)->pluck('Ten')->toArray();
        $foods = $this->foodPreferenceService->filterBlockedFoods($foods, $foodPreferences);
        $likes = $this->foodPreferenceService->filterBlockedFoods($foodPreferences['likes'] ?? [], $foodPreferences);
        $foods = array_values(array_unique(array_merge($likes, $foods)));

        if (count($foods) < self::MAX_SUGGESTIONS) {
            $fallback = [
                'ca hap gung voi rau',
                'bun thit nac nhieu rau',
                'chao yen mach thit bam rau cu',
                'sua dau nanh khong duong kem chuoi',
                'bo nac xao bong cai it dau',
            ];

            $foods = array_values(array_unique(array_merge(
                $foods,
                $this->foodPreferenceService->filterBlockedFoods($fallback, $foodPreferences)
            )));
        }

        return array_slice($foods, 0, self::MAX_SUGGESTIONS);
    }
}
