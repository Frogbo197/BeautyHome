<?php

namespace App\Service\HealthAnalysis;

class WorkoutRecommendationService
{
    public const MAX_SUGGESTIONS = 4;

    public function recommend(): array
    {
        $workouts = [
            'Di bo',
            'Chay bo',
            'Yoga',
            'Dap xe',
            'HIIT',
            'Nhay day',
            'Gym',
            'Boi loi',
        ];

        shuffle($workouts);

        return array_slice($workouts, 0, self::MAX_SUGGESTIONS);
    }
}
