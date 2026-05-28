<?php

namespace App\Actions;

use App\DTO\HealthAnalysisResultDTO;
use App\Service\HealthAnalysis\HealthAnalysisPersistenceService;
use App\Service\HealthAnalysis\HealthAnalysisPresenter;
use App\Service\HealthAnalysis\HealthContextBuilder;
use App\Service\HealthAnalysis\NutritionRecommendationService;
use App\Service\HealthAnalysis\WorkoutRecommendationService;
use App\Service\HealthScoreService;
use Illuminate\Support\Facades\Log;

class AnalyzeHealthAction
{
    public const AI_MODEL_VERSION = 'gemma:2b';

    public function __construct(
        private readonly HealthContextBuilder $contextBuilder,
        private readonly HealthScoreService $healthScoreService,
        private readonly NutritionRecommendationService $nutritionRecommendations,
        private readonly WorkoutRecommendationService $workoutRecommendations,
        private readonly HealthAnalysisPresenter $presenter,
        private readonly HealthAnalysisPersistenceService $persistence,
    ) {
    }

    public function execute(array $validated): HealthAnalysisResultDTO
    {
        $context = $this->contextBuilder->build((int) $validated['user_id']);

        $healthScore = $this->healthScoreService->calculate(
            $context->bmi,
            $context->totalCalories,
            $context->totalActivityCalories,
            $context->age,
            $context->sex,
            $context->activityLevel
        );

        $nutritionRecommendations = $this->nutritionRecommendations->recommend(
            $context->foodPreferences,
            $context->bmi,
            $context->totalProtein,
            (float) ($healthScore['score'] ?? 0)
        );

        $workoutRecommendations = $this->workoutRecommendations->recommend();

        $result = $this->presenter->makeResult(
            $context,
            $healthScore,
            $nutritionRecommendations,
            $workoutRecommendations
        );

        Log::info('AI analyze - dung template', [
            'user_id' => $context->userId,
            'score' => $result->healthScore,
            'bmi' => $result->bmi,
            'score_context' => $result->scoreContext,
        ]);

        $this->persistence->save(
            $context,
            $result,
            (string) $validated['prompt'],
            self::AI_MODEL_VERSION
        );

        return $result;
    }
}
