<?php

namespace App\Services\AI;

class NutritionService
{
    public function estimateCalories($foodName)
    {
        $foods = [
            'phở bò' => 450,
            'cơm trắng' => 250,
            'bún bò' => 500,
            'trà sữa' => 350,
            'gà rán' => 400,
            'bánh mì' => 300,
            'pizza' => 700,
            'hamburger' => 650,
        ];

        $foodName = strtolower($foodName);

        foreach ($foods as $food => $calo) {
            if (str_contains($foodName, $food)) {
                return $calo;
            }
        }

        return 200;
    }
}