<?php

namespace App\DTO;

class HealthAnalysisResultDTO
{
    public function __construct(
        public readonly int $healthScore,
        public readonly string $healthStatus,
        public readonly string $scoreContext,
        public readonly float $bmi,
        public readonly float $caloriesIn,
        public readonly float $caloriesOut,
        public readonly float $calorieBalance,
        public readonly float $protein,
        public readonly float $carb,
        public readonly float $fat,
        public readonly int $steps,
        public readonly int $activityMinutes,
        public readonly array $nutritionRecommendations,
        public readonly array $workoutRecommendations,
        public readonly string $result,
    ) {
    }

    public function toResponseArray(): array
    {
        return [
            'success' => true,
            'health_score' => $this->healthScore,
            'health_status' => $this->healthStatus,
            'score_context' => $this->scoreContext,
            'bmi' => $this->bmi,
            'calories' => [
                'in' => $this->caloriesIn,
                'out' => $this->caloriesOut,
                'balance' => $this->calorieBalance,
            ],
            'nutrition' => [
                'protein' => $this->protein,
                'carb' => $this->carb,
                'fat' => $this->fat,
            ],
            'steps' => $this->steps,
            'activity_minutes' => $this->activityMinutes,
            'nutrition_recommendations' => $this->nutritionRecommendations,
            'workout_recommendations' => $this->workoutRecommendations,
            'result' => $this->result,
            'medical_disclaimer' => 'Noi dung chi mang tinh ho tro thong tin, khong thay the chan doan hoac dieu tri cua bac si.',
        ];
    }
}
