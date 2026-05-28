<?php

namespace App\Service\HealthAnalysis;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FoodPreferenceService
{
    public const FOOD_PREF_TABLE = 'SoThichThucPhamNguoiDung';

    public function __construct(private readonly TextNormalizer $normalizer)
    {
    }

    public function forUser(int $userId): array
    {
        $prefs = [
            'likes' => [],
            'dislikes' => [],
            'allergies' => [],
            'blocked' => [],
        ];

        if (!Schema::hasTable(self::FOOD_PREF_TABLE)) {
            return $prefs;
        }

        $rows = DB::table(self::FOOD_PREF_TABLE)
            ->where('NguoiDungID', $userId)
            ->get(['FoodName', 'PreferenceType']);

        foreach ($rows as $row) {
            $food = trim((string) ($row->FoodName ?? ''));
            if ($food === '') {
                continue;
            }

            $type = $this->normalizer->plainText((string) ($row->PreferenceType ?? ''));
            if (str_contains($type, 'allergy') || str_contains($type, 'di ung')) {
                $prefs['allergies'][] = $food;
            } elseif (str_contains($type, 'dislike') || str_contains($type, 'ghet') || str_contains($type, 'khong an')) {
                $prefs['dislikes'][] = $food;
            } elseif (str_contains($type, 'like') || str_contains($type, 'thich')) {
                $prefs['likes'][] = $food;
            }
        }

        $prefs['likes'] = array_values(array_unique($prefs['likes']));
        $prefs['dislikes'] = array_values(array_unique($prefs['dislikes']));
        $prefs['allergies'] = array_values(array_unique($prefs['allergies']));
        $prefs['blocked'] = array_values(array_unique(array_merge($prefs['allergies'], $prefs['dislikes'])));

        return $prefs;
    }

    public function filterBlockedFoods(array $foods, array $foodPreferences): array
    {
        $blocked = $this->expandedBlockedTerms($foodPreferences['blocked'] ?? []);
        if (empty($blocked)) {
            return array_values($foods);
        }

        return array_values(array_filter($foods, function ($food) use ($blocked) {
            return !$this->foodMatchesAnyTerm((string) $food, $blocked);
        }));
    }

    public function expandedBlockedTerms(array $terms): array
    {
        $expanded = [];

        foreach ($terms as $term) {
            $term = trim((string) $term);
            if ($term === '') {
                continue;
            }

            $expanded[] = $term;
            $plain = $this->normalizer->plainText($term);

            if (str_contains($plain, 'hai san')) {
                $expanded = array_merge($expanded, ['tom', 'cua', 'muc', 'ngheu', 'so', 'oc', 'hau']);
            }

            if ($plain === 'sua' || str_contains($plain, 'sua bo')) {
                $expanded = array_merge($expanded, ['sua chua', 'pho mai', 'whey']);
            }
        }

        return array_values(array_unique($expanded));
    }

    public function foodMatchesAnyTerm(string $food, array $terms): bool
    {
        $foodPlain = $this->normalizer->plainText($food);
        if ($foodPlain === '') {
            return false;
        }

        foreach ($terms as $term) {
            $termPlain = $this->normalizer->plainText((string) $term);
            if ($termPlain === '') {
                continue;
            }

            if (str_contains($foodPlain, $termPlain) || str_contains($termPlain, $foodPlain)) {
                return true;
            }
        }

        return false;
    }
}
