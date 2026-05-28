<?php

namespace App\Service\HealthAnalysis;

use App\DTO\HealthAnalysisResultDTO;
use App\DTO\HealthContextDTO;

class HealthAnalysisPresenter
{
    public function __construct(private readonly HealthMetricCalculator $metrics)
    {
    }

    public function makeResult(
        HealthContextDTO $context,
        array $healthScore,
        array $nutritionRecommendations,
        array $workoutRecommendations
    ): HealthAnalysisResultDTO {
        $score = (int) ($healthScore['score'] ?? 0);
        [$scoreContext] = $this->metrics->scoreContext($score);
        [$bmiLabel, $bmiAdvice] = $this->metrics->bmiLabelAndAdvice($context->bmi);
        $calorieContext = $this->metrics->calorieContext($context->totalCalories, $context->totalActivityCalories);
        $userName = $context->userName();

        $line1 = match (true) {
            $score >= 80 => "Hom nay suc khoe cua ban ({$userName}) rat on ap do, {$score}/100 luon",
            $score >= 60 => "Hom nay suc khoe cua ban ({$userName}) kha on nha, {$score}/100",
            $score >= 40 => "Hom nay suc khoe cua ban ({$userName}) o muc trung binh thoi, {$score}/100 ne",
            default => "Hom nay suc khoe cua ban ({$userName}) hoi yeu roi do, {$score}/100 thoi",
        };

        if ($context->bmi === 0.0) {
            $line2 = 'Chua co du lieu BMI, cap nhat can nang va chieu cao de theo doi chinh xac hon nha.';
        } else {
            $line2 = "BMI {$context->bmi} dang {$bmiLabel} - {$bmiAdvice}.";
        }

        $topFood = $nutritionRecommendations[0] ?? 'rau cu';
        $topWorkout = $workoutRecommendations[0] ?? 'di bo';

        if ($context->totalCalories === 0.0) {
            $line3 = "Hom nay chua log bua an, thu an {$topFood} va {$topWorkout} mot chut nha!";
        } elseif ($context->totalActivityCalories === 0.0) {
            $line3 = "Ban da nap {$context->totalCalories} calo nhung chua van dong gi - thu {$topWorkout} 30 phut xem sao nha.";
        } else {
            $line3 = "Calo {$calorieContext} - ban nho an {$topFood} va {$topWorkout} them nhe!";
        }

        $result = "{$line1} {$line2} {$line3}";

        return new HealthAnalysisResultDTO(
            healthScore: $score,
            healthStatus: (string) ($healthScore['status'] ?? ''),
            scoreContext: $scoreContext,
            bmi: $context->bmi,
            caloriesIn: $context->totalCalories,
            caloriesOut: $context->totalActivityCalories,
            calorieBalance: $context->totalCalories - $context->totalActivityCalories,
            protein: $context->totalProtein,
            carb: $context->totalCarb,
            fat: $context->totalFat,
            steps: $context->totalSteps,
            activityMinutes: $context->activityMinutes,
            nutritionRecommendations: $nutritionRecommendations,
            workoutRecommendations: $workoutRecommendations,
            result: $result,
        );
    }
}
