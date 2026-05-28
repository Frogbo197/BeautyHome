<?php

namespace App\Service\HealthAnalysis;

class TextNormalizer
{
    public function plainText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $ascii !== false ? $ascii : $value;
        $value = preg_replace('/[^a-z0-9\s\/_-]+/i', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', mb_strtolower($value, 'UTF-8')) ?? $value);
    }
}
