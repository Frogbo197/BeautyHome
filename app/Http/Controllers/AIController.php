<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Service\OpenRouterService;
use App\Service\NutritionService;
use App\Service\HealthScoreService;
use App\Service\RecommendationService;

use App\Models\TaiKhoan;
use App\Models\BuaAn;
use App\Models\ActivityLog;
use App\Models\HoSoNguoiDung;
use App\Models\HoSoSucKhoe;
use App\Models\PhanTichSucKhoeAI;
use App\Models\SoThichNguoiDung;
use App\Models\DiemSucKhoe;
use App\Models\ChiSoSucKhoe;
use App\Models\GoiYDinhDuong;
use App\Models\GoiYLuyenTap;
use App\Models\DailyHealthSummary;
use App\Models\ThucPham;

use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AIController extends Controller
{
    const AI_MODEL_VERSION          = 'gemma:2b';
    const MAX_NUTRITION_SUGGESTIONS = 5;   
    const MAX_WORKOUT_SUGGESTIONS   = 4;  
    const FOOD_PREF_TABLE           = 'SoThichThucPhamNguoiDung';

    protected $ai;
    protected $nutritionService;
    protected $healthScoreService;
    protected $recommendationService;

    public function __construct(
        OpenRouterService $ai,
        NutritionService $nutritionService,
        HealthScoreService $healthScoreService,
        RecommendationService $recommendationService
    ) {
        $this->ai                    = $ai;
        $this->nutritionService      = $nutritionService;
        $this->healthScoreService    = $healthScoreService;
        $this->recommendationService = $recommendationService;
    }

    public function analyze(Request $request)
    {
        try {

            /*
            |--------------------------------------------------------------------------
            | VALIDATE
            |--------------------------------------------------------------------------
            */

            $validated = $request->validate([
                'user_id' => 'required|integer|exists:taikhoan,ID',
                'prompt'  => 'required|string|max:1000',
            ]);

            $userId = $validated['user_id'];

            /*
            |--------------------------------------------------------------------------
            | USER
            |--------------------------------------------------------------------------
            */

            $user = TaiKhoan::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'error'   => 'User not found',
                ], 404);
            }

            /*
            |--------------------------------------------------------------------------
            | PROFILE
            |--------------------------------------------------------------------------
            */

            $profile     = HoSoNguoiDung::where('NguoiDungID', $userId)->first();
            $health      = HoSoSucKhoe::where('NguoiDungID', $userId)->first();
            $chiSo       = ChiSoSucKhoe::where('NguoiDungID', $userId)->latest('ID')->first();
            $preferences = SoThichNguoiDung::where('NguoiDung', $userId)->first();
            $foodPreferences = $this->foodPreferenceContext($userId);

            /*
            |--------------------------------------------------------------------------
            | BMI
            |--------------------------------------------------------------------------
            */

            $heightCm = $profile?->ChieuCao ?? 0;
            $weight   = $profile?->CanNang  ?? 0;
            $heightM  = $heightCm > 0 ? $heightCm / 100 : 1.7;

            if ($chiSo && $chiSo->BMI) {
                $bmi = $chiSo->BMI;
            } elseif ($weight > 0) {
                $bmi = round($weight / ($heightM * $heightM), 1);
            } else {
                $bmi = 0;
            }

            /*
            |--------------------------------------------------------------------------
            | AGE
            |--------------------------------------------------------------------------
            */

            $age = 20;

            if (!empty($profile?->NgaySinh)) {
                $parsed = Carbon::parse($profile?->NgaySinh)->age;
                $age    = ($parsed > 0 && $parsed < 120) ? $parsed : 20;
            }

            /*
            |--------------------------------------------------------------------------
            | SEX
            |--------------------------------------------------------------------------
            */

            $gioiTinh = strtolower(trim($profile?->GioiTinh ?? 'nam'));
            $sex      = $gioiTinh === 'nam' ? 'M' : 'F';

            /*
            |--------------------------------------------------------------------------
            | MEALS
            |--------------------------------------------------------------------------
            */

            $meals         = BuaAn::with('chiTiet.thucPham')->where('NguoiDungID', $userId)->latest('ID')->limit(5)->get();
            $mealSummary   = '';
            $totalCalories = 0;
            $totalProtein = 0;
            $totalCarb = 0;
            $totalFat = 0;

            foreach ($meals as $meal) {
                $foodName = trim($meal->TenMonAn ?? '');
                if (empty($foodName)) {
                    $foodName = 'Không rõ';
                }

                  /*
                |--------------------------------------------------------------------------
                | FOOD FROM DATABASE
                |--------------------------------------------------------------------------
                */

                    $detail = $meal->chiTiet->first();
                    $food = $detail?->thucPham;
                    $calories = (float) ($detail?->TongCalo ?? $food?->Calo ?? 0);
                    $protein = (float) ($detail?->TongProtein ?? $food?->Protein ?? 0);
                    $carb = (float) ($detail?->TongCarb ?? $food?->Carb ?? 0);
                    $fat = (float) ($detail?->TongFat ?? $food?->ChatBeo ?? 0);
                    $mealSummary .= "- {$foodName} ({$calories} calo)\n";
                    $totalCalories += $calories;
                    $totalProtein += $protein;
                    $totalCarb += $carb;
                    $totalFat += $fat;
            }

            if (empty($mealSummary)) {
                $mealSummary = 'Chưa có dữ liệu bữa ăn hôm nay.';
            }

            /*
            |--------------------------------------------------------------------------
            | ACTIVITIES
            |--------------------------------------------------------------------------
            */

            $activities            = ActivityLog::where('NguoiDungID', $userId)->latest('ID')->limit(5)->get();
            $activitySummary       = '';
            $totalActivityCalories = 0;
            $activityMinutes = 0;
            $totalSteps = 0;

            foreach ($activities as $act) {
                $activityName          = $act->TenHoatDong   ?? 'Khác';
                $activityCalories      = $act->CaloriesBurned ?? 0;
                $duration              = $act->ThoiLuongPhut ?? 0;
                $Steps                 = $act->Steps ?? 0;
                $activitySummary       .= "- {$activityName}: {$activityCalories} calo\n";
                $activityMinutes             += $duration;
                $totalSteps            += $Steps;
                $totalActivityCalories += $activityCalories;
            }

            if (empty($activitySummary)) {
                $activitySummary = 'Chưa có hoạt động nào được ghi nhận.';
            }

            /*
            |--------------------------------------------------------------------------
            | ACTIVITY LEVEL
            |--------------------------------------------------------------------------
            */

            if ($totalActivityCalories >= 500) {
                $activityLevel = 'active';
            } elseif ($totalActivityCalories < 100) {
                $activityLevel = 'sedentary';
            } else {
                $activityLevel = 'moderate';
            }

            /*
            |--------------------------------------------------------------------------
            | HEALTH SCORE
            |--------------------------------------------------------------------------
            */

            $healthScore =
                $this->healthScoreService
                    ->calculate(

                        $bmi,

                        $totalCalories,

                        $totalActivityCalories,

                        $age,

                        $sex,

                        $activityLevel
                    );
            /*
            |--------------------------------------------------------------------------
            | HEALTH SCORE SUMMARY
            |--------------------------------------------------------------------------
            */

            $score = $healthScore['score'];

            if ($score >= 80) {
                $scoreContext = 'tốt';
                $scoreSummary = "Sức khỏe đang rất tốt, duy trì nhé!";
            } elseif ($score >= 60) {
                $scoreContext = 'khá';
                $scoreSummary = "Sức khỏe khá ổn, còn có thể cải thiện thêm.";
            } elseif ($score >= 40) {
                $scoreContext = 'trung bình';
                $scoreSummary = "Cần chú ý hơn về ăn uống và vận động.";
            } else {
                $scoreContext = 'yếu';
                $scoreSummary = "Cần điều chỉnh lối sống sớm.";
            }

            /*
            |--------------------------------------------------------------------------
            | BMI LABEL — hardcode đánh giá đúng chuẩn WHO
            | Tránh để AI tự phán và hallucinate thông tin y tế sai
            |--------------------------------------------------------------------------
            */

            if ($bmi === 0) {
                $bmiLabel  = 'chưa có dữ liệu';
                $bmiAdvice = 'cập nhật chiều cao và cân nặng nha          VGFC';
            } elseif ($bmi < 18.5) {
                $bmiLabel  = 'thiếu cân';
                $bmiAdvice = 'nên ăn nhiều hơn và bổ sung dinh dưỡng';
            } elseif ($bmi <= 24.9) {
                $bmiLabel  = 'bình thường';
                $bmiAdvice = 'BMI đang ở mức lý tưởng, duy trì tốt 🔥';
            } elseif ($bmi <= 29.9) {
                $bmiLabel  = 'thừa cân';
                $bmiAdvice = 'nên tăng vận động và kiểm soát khẩu phần ăn';
            } else {
                $bmiLabel  = 'béo phì';
                $bmiAdvice = 'nên tham khảo chuyên gia dinh dưỡng, ăn uống hợp lý và tăng vận động';
            }

            /*
            |--------------------------------------------------------------------------
            | CALORIE CONTEXT
            |--------------------------------------------------------------------------
            */

            $calorieBalance =
                $totalCalories -
                $totalActivityCalories;

            if ($totalCalories === 0) {

                $calorieContext =
                    'chưa ghi nhận bữa ăn hôm nay';

            } elseif (
                $totalActivityCalories === 0
            ) {

                $calorieContext =
                    "đã nạp {$totalCalories} calo nhưng chưa vận động";

            } elseif (
                $calorieBalance > 300
            ) {

                $calorieContext =
                    "đang dư {$calorieBalance} calo";

            } elseif (
                $calorieBalance < -300
            ) {

                $calorieContext =
                    "đang thiếu hụt "
                    . abs($calorieBalance)
                    . " calo";

            } else {

                $calorieContext =
                    "calo khá cân bằng";
            }


            /*
            |--------------------------------------------------------------------------
            | RECOMMENDATIONS — cap số lượng gợi ý
            |--------------------------------------------------------------------------
            */

            $nutritionRecommendations = $this->nutritionRecommendations(
                $foodPreferences,
                (float) $bmi,
                (float) $totalProtein,
                (float) $score
            );

            $allWorkoutRecommendations = [

                'Đi bộ',
                'Chạy bộ',
                'Yoga',
                'Đạp xe',
                'HIIT',
                'Nhảy dây',
                'Gym',
                'Bơi lội'
            ];

            shuffle(
                $allWorkoutRecommendations
            );

            $workoutRecommendations =
                array_slice(
                    $allWorkoutRecommendations,
                    0,
                    self::MAX_WORKOUT_SUGGESTIONS
                );

            /*
            |--------------------------------------------------------------------------
            | RESULT TEMPLATE
            |--------------------------------------------------------------------------
            */

            $userName = $profile?->Ten ?? $user->Email;

            // Dùng "bạn" trong câu — tránh AI tự chèn đại từ "anh ta / cô ấy"
            $line1 = match(true) {
                $score >= 80 => "Hôm nay sức khỏe của bạn ({$userName}) rất ổn áp đó, {$score}/100 luôn 😭🔥",
                $score >= 60 => "Hôm nay sức khỏe của bạn ({$userName}) khá ổn nha, {$score}/100 👍",
                $score >= 40 => "Hôm nay sức khỏe của bạn ({$userName}) ở mức trung bình thôi, {$score}/100 nè 😅",
                default      => "Hôm nay sức khỏe của bạn ({$userName}) hơi yếu rồi đó, {$score}/100 thôi 😬",
            };

            if ($bmi === 0) {
                $line2 = "Chưa có dữ liệu BMI, cập nhật cân nặng và chiều cao để theo dõi chính xác hơn nha.";
            } else {
                $line2 = "BMI {$bmi} đang {$bmiLabel} — {$bmiAdvice}.";
            }
           
            $topFood    = $nutritionRecommendations[0] ?? 'rau củ';
            $topWorkout = $workoutRecommendations[0]   ?? 'đi bộ';

            if ($totalCalories === 0) {
                $line3 = "Hôm nay chưa log bữa ăn, thử ăn {$topFood} và {$topWorkout} một chút nha!";
            } elseif ($totalActivityCalories === 0) {
                $line3 = "Bạn đã nạp {$totalCalories} calo nhưng chưa vận động gì — thử {$topWorkout} 30 phút xem sao nha 👀";
            } else {
                $line3 = "Calo {$calorieContext} — bạn nhớ ăn {$topFood} và {$topWorkout} thêm nhé!";
            }

            $result = "{$line1} {$line2} {$line3}";

            Log::info('AI analyze - dùng template', [
                'user_id'       => $userId,
                'score'         => $score,
                'bmi'           => $bmi,
                'score_context' => $scoreContext,
            ]);

            /*
            |--------------------------------------------------------------------------
            | SAVE HEALTH SCORE
            |--------------------------------------------------------------------------
            */

            DiemSucKhoe::create([
                'NguoiDungID' => $userId,
                'Diem'        => $score,
                'NgayTinh'    => now(),
                'NhanXetAI'   => $result,
            ]);

            /*
            |--------------------------------------------------------------------------
            | CLEAR OLD RECOMMENDATIONS
            |--------------------------------------------------------------------------
            */

            GoiYDinhDuong::where('NguoiDungID', $userId)->delete();
            GoiYLuyenTap::where('NguoiDungID', $userId)->delete();

            /*
            |--------------------------------------------------------------------------
            | SAVE FOOD RECOMMENDATIONS
            |--------------------------------------------------------------------------
            */

            foreach ($nutritionRecommendations as $food) {
                GoiYDinhDuong::create([
                    'NguoiDungID' => $userId,
                    'MonAn'       => $food,
                    'LoaiBuaAn'   => 'Khuyến nghị AI',
                    'Ngay'        => now(),
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE WORKOUT RECOMMENDATIONS
            |--------------------------------------------------------------------------
            */

            foreach ($workoutRecommendations as $workout) {
                GoiYLuyenTap::create([
                    'NguoiDungID'         => $userId,
                    'TenBaiTap'           => $workout,
                    'ThoiLuong'           => 30,
                    'CaloDot'             => 200,
                    'NgayTao'             => now(),
                    'RecommendationScore' => 90,
                    'DifficultyLevel' =>'Trung bình',
                    'GoalType' =>optional($preferences)->MucTieu ?? 'General',
                    'GeneratedReason' =>'AI recommendation',
                    'SourceType' => 'AI',
                    'ModelVersion'        => self::AI_MODEL_VERSION,
                ]);
            }

            /*
            |--------------------------------------------------------------------------
            | SAVE AI ANALYSIS
            |--------------------------------------------------------------------------
            */

            PhanTichSucKhoeAI::create([
                'NguoiDungID'  => $userId,
                'LoaiPhanTich' => 'TongQuat',
                'KetQua'       => $result,
                'prompt'       => $validated['prompt'],
                'Model'        => self::AI_MODEL_VERSION,
            ]);

            /*
            |--------------------------------------------------------------------------
            | SAVE DAILY SUMMARY — updateOrCreate tránh duplicate cùng ngày
            |--------------------------------------------------------------------------
            */

            DailyHealthSummary::updateOrCreate(
                [
                    'NguoiDungID' => $userId,
                    'Ngay'        => now()->toDateString(),
                ],
                [
                    'TongCaloVao'  => $totalCalories,
                    'TongCaloRa' => $totalActivityCalories,
                    'MucTieu' => $calorieBalance,
                    'TongProtein' => $totalProtein,
                    'TongCarb' =>$totalCarb,
                    'TongChatBeo' =>$totalFat,
                    'TongLuongNuoc' =>2000,
                    'TongBuocDi' =>$totalSteps,
                    'ThoiGianHoatDong' =>$activityMinutes,
                    'DiemSucKhoe' =>$score,
                    'AIPhanTich' => $result,
                    'TrangThaiHoanThanh' =>$healthScore['status']
                ]
            );

            /*
            |--------------------------------------------------------------------------
            | RESPONSE — UPGRADE
            |--------------------------------------------------------------------------
            */

            return response()->json([
                'success'                   => true,
                'health_score'              => $score,
                'health_status'             => $healthScore['status'],
                'score_context'             => $scoreContext,
                'bmi'                       => $bmi,
                'calories' => [
                    'in'      => $totalCalories,
                    'out'     => $totalActivityCalories,
                    'balance' => $totalCalories - $totalActivityCalories,
                ],
                'nutrition' => [
                    'protein' => $totalProtein,
                    'carb' => $totalCarb,
                    'fat' =>$totalFat
                ],
                'steps' =>$totalSteps,
                'activity_minutes' =>$activityMinutes,
                'nutrition_recommendations' => $nutritionRecommendations,
                'workout_recommendations'   => $workoutRecommendations,
                'result'                    => $result,
            ]);

        } catch (\Exception $e) {

            Log::error('AI analyze error', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    private function nutritionRecommendations(array $foodPreferences, float $bmi, float $protein, float $score): array
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

        $foods = $query
            ->limit(40)
            ->pluck('Ten')
            ->toArray();

        $foods = $this->filterBlockedFoods($foods, $foodPreferences);
        $likes = $this->filterBlockedFoods($foodPreferences['likes'] ?? [], $foodPreferences);

        $foods = array_values(array_unique(array_merge($likes, $foods)));

        if (count($foods) < self::MAX_NUTRITION_SUGGESTIONS) {
            $fallback = [
                'ca hap gung voi rau',
                'bun thit nac nhieu rau',
                'chao yen mach thit bam rau cu',
                'sua dau nanh khong duong kem chuoi',
                'bo nac xao bong cai it dau',
            ];
            $foods = array_values(array_unique(array_merge($foods, $this->filterBlockedFoods($fallback, $foodPreferences))));
        }

        return array_slice($foods, 0, self::MAX_NUTRITION_SUGGESTIONS);
    }

    private function foodPreferenceContext(int $userId): array
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

            $type = $this->plainText((string) ($row->PreferenceType ?? ''));
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

    private function filterBlockedFoods(array $foods, array $foodPreferences): array
    {
        $blocked = $this->expandedBlockedTerms($foodPreferences['blocked'] ?? []);
        if (empty($blocked)) {
            return array_values($foods);
        }

        return array_values(array_filter($foods, function ($food) use ($blocked) {
            return !$this->foodMatchesAnyTerm((string) $food, $blocked);
        }));
    }

    private function expandedBlockedTerms(array $terms): array
    {
        $expanded = [];

        foreach ($terms as $term) {
            $term = trim((string) $term);
            if ($term === '') {
                continue;
            }

            $expanded[] = $term;
            $plain = $this->plainText($term);

            if (str_contains($plain, 'hai san')) {
                $expanded = array_merge($expanded, ['tom', 'cua', 'muc', 'ngheu', 'so', 'oc', 'hau']);
            }

            if ($plain === 'sua' || str_contains($plain, 'sua bo')) {
                $expanded = array_merge($expanded, ['sua chua', 'pho mai', 'whey']);
            }
        }

        return array_values(array_unique($expanded));
    }

    private function foodMatchesAnyTerm(string $food, array $terms): bool
    {
        $foodPlain = $this->plainText($food);
        if ($foodPlain === '') {
            return false;
        }

        foreach ($terms as $term) {
            $termPlain = $this->plainText((string) $term);
            if ($termPlain === '') {
                continue;
            }

            if (str_contains($foodPlain, $termPlain) || str_contains($termPlain, $foodPlain)) {
                return true;
            }
        }

        return false;
    }

    private function plainText(string $value): string
    {
        $value = mb_strtolower(trim($value), 'UTF-8');
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $value);
        $value = $ascii !== false ? $ascii : $value;
        $value = preg_replace('/[^a-z0-9\s\/_-]+/i', ' ', $value) ?? $value;

        return trim(preg_replace('/\s+/', ' ', mb_strtolower($value, 'UTF-8')) ?? $value);
    }
}
