<?php
namespace App\Http\Controllers;

use Illuminate\Http\Request;

use App\Models\DailyHealthSummary;
use App\Models\DiemSucKhoe;
use App\Models\ChiSoSucKhoe;
use App\Models\Water;
use App\Models\GoiYDinhDuong;
use App\Models\GoiYLuyenTap;
use App\Service\DailySummaryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DashboardController extends Controller
{
    public function index($userId)
    {
        try {
            $today = now('Asia/Ho_Chi_Minh')->toDateString();
            $todayTotals = app(DailySummaryService::class)->calculate((int) $userId, $today);

            /*
            |--------------------------------------------------------------------------
            | LATEST HEALTH SCORE
            |--------------------------------------------------------------------------
            */

            $latestScore =
                DiemSucKhoe::where(
                    'NguoiDungID',
                    $userId
                )
                ->latest('ID')
                ->first();

            /*
            |--------------------------------------------------------------------------
            | BMI
            |--------------------------------------------------------------------------
            */

            $latestBMI =
                ChiSoSucKhoe::where(
                    'NguoiDungID',
                    $userId
                )
                ->whereNotNull('BMI')
                ->where('BMI', '>', 0)
                ->latest('ID')
                ->value('BMI');

            $profile = DB::table('hosonguoidung')
                ->where('NguoiDungID', $userId)
                ->first();

            $profileBMI = null;
            if ($profile?->ChieuCao && $profile?->CanNang) {
                $heightM = ((float) $profile->ChieuCao) / 100;
                if ($heightM > 0) {
                    $profileBMI = round(((float) $profile->CanNang) / ($heightM * $heightM), 1);
                }
            }

            /*
            |--------------------------------------------------------------------------
            | WATER
            |--------------------------------------------------------------------------
            */

            $todayWater =
                Water::where(
                    'NguoiDungID',
                    $userId
                )
                ->whereDate(
                    'Ngay',
                    $today
                )
                ->sum('LuongNuoc');

            /*
            |--------------------------------------------------------------------------
            | DAILY SUMMARY
            |--------------------------------------------------------------------------
            */

            $summary = null;
            if (Schema::hasTable('tomtatsuckhoehangngay')) {
                $summary = DailyHealthSummary::where(
                    'NguoiDungID',
                    $userId
                )
                ->whereDate(
                    'Ngay',
                    $today
                )
                ->first();
            }

            $medicineTakenToday = DB::table('lichdungthuoc')
                ->where('NguoiDungID', $userId)
                ->whereDate('ThoiGian', $today)
                ->whereIn('TrangThai', ['DaUong', 'da_uong', 'ÄÃ£ uá»‘ng', 'Da uong'])
                ->count();
            if ($medicineTakenToday === 0) {
                $medicineTakenToday = 0;
            }

            /*
            |--------------------------------------------------------------------------
            | RECOMMENDATIONS
            |--------------------------------------------------------------------------
            */

            $nutrition =
                GoiYDinhDuong::where(
                    'NguoiDungID',
                    $userId
                )
                ->latest('ID')
                ->limit(5)
                ->get();

            $workouts =
                GoiYLuyenTap::where(
                    'NguoiDungID',
                    $userId
                )
                ->latest('ID')
                ->limit(5)
                ->get();

            /*
            |--------------------------------------------------------------------------
            | HEALTH TREND
            |--------------------------------------------------------------------------
            */

            if (Schema::hasTable('tomtatsuckhoehangngay')) {
                $trend = DailyHealthSummary::where(
                    'NguoiDungID',
                    $userId
                )
                ->latest('Ngay')
                ->limit(7)
                ->get([
                    'Ngay',
                    'DiemSucKhoe',
                    'TongCaloVao',
                    'TongCaloRa',
                    'TongLuongNuoc'
                ]);
            } else {
                $trend = collect();
            }

            /*
            |--------------------------------------------------------------------------
            | RESPONSE
            |--------------------------------------------------------------------------
            */

            return response()->json([

                'success' => true,

                'date' => $today,

                'has_health_score' =>
                    $latestScore !== null &&
                    $latestScore->Diem !== null,

                'health_score' =>
                    $latestScore?->Diem,

                'ai_comment' =>
                    $latestScore?->NhanXetAI
                    ?? '',

                'user' => [
                    'name' => $profile->Ten ?? '',
                    'avatar' => $profile->AnhDaiDien ?? '',
                ],

                'bmi' =>
                    $latestBMI ?? $profileBMI,

                'today_water_ml' =>
                    $todayTotals['WaterML'] ?: $todayWater,

                'water_goal_ml' =>
                    $this->waterGoal((int) $userId),

                'today_summary' => [

                    'calories_in' =>
                        $todayTotals['CaloriesIn'],

                    'calories_out' =>
                        $todayTotals['CaloriesOut'],

                    'goal_status' =>
                        $summary->TrangThaiHoanThanh
                        ?? 'KhÃ´ng rÃµ'
                ],

                'medicine_taken_today' =>
                    $medicineTakenToday,

                'nutrition_recommendations' =>
                    $nutrition,

                'workout_recommendations' =>
                    $workouts,

                'health_trend' =>
                    $trend
            ]);

        } catch (\Exception $e) {

            return response()->json([

                'success' => false,

                'error' =>
                    $e->getMessage()

            ], 500);
        }
    }

    private function waterGoal(int $userId): int
    {
        if (Schema::hasTable('user_goals')) {
            $goal = DB::table('user_goals')
                ->where('NguoiDungID', $userId)
                ->where('Loai', 'UongNuoc')
                ->value('GiaTri');
            if ($goal) return (int) $goal;
        }
        if (Schema::hasTable('muctieusuckhoe')) {
            $goal = DB::table('muctieusuckhoe')
                ->where('NguoiDungID', $userId)
                ->where(function ($query) {
                    $query
                        ->whereIn('LoaiMucTieu', ['Nuoc', 'UongNuoc', 'Uong nuoc'])
                        ->orWhere('TenMucTieu', 'like', '%nuoc%')
                        ->orWhere('TenMucTieu', 'like', '%nước%');
                })
                ->whereNotNull('GiaTriMucTieu')
                ->latest('ID')
                ->value('GiaTriMucTieu');
            if ($goal) return (int) $goal;
        }
        return 1500;
    }
}
