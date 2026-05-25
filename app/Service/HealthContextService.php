<?php

namespace App\Service;

use App\Models\DiemSucKhoe;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HealthContextService
{
    public function build(int $userId): array
    {
        $today = now('Asia/Ho_Chi_Minh')->toDateString();
        $profile = Schema::hasTable('hosonguoidung')
            ? DB::table('hosonguoidung')->where('NguoiDungID', $userId)->first()
            : null;
        $health = Schema::hasTable('hososuckhoe')
            ? DB::table('hososuckhoe')->where('NguoiDungID', $userId)->first()
            : null;
        $latestIndex = Schema::hasTable('chisosuckhoe')
            ? DB::table('chisosuckhoe')->where('NguoiDungID', $userId)->orderByDesc('ID')->first()
            : null;

        $heightCm = (float) ($profile->ChieuCao ?? 0);
        $weight = (float) ($latestIndex->CanNang ?? $profile->CanNang ?? 0);
        $heightM = $heightCm > 0 ? $heightCm / 100 : 0;
        $bmi = (float) ($latestIndex->BMI ?? 0);
        if ($bmi <= 0 && $weight > 0 && $heightM > 0) {
            $bmi = round($weight / ($heightM * $heightM), 1);
        }

        $age = 25;
        if (!empty($profile->NgaySinh)) {
            try {
                $parsed = Carbon::parse($profile->NgaySinh)->age;
                $age = ($parsed > 0 && $parsed < 120) ? $parsed : 25;
            } catch (\Throwable) {
                $age = 25;
            }
        }

        [$caloriesIn, $protein, $carb, $fat, $mealSummary] = $this->mealContext($userId, $today);
        [$caloriesOut, $minutes, $steps, $activitySummary] = $this->activityContext($userId, $today);
        $balance = $caloriesIn - $caloriesOut;
        [$bmiLabel, $bmiAdvice] = $this->bmiLabelAndAdvice($bmi);
        $scoreRecord = DiemSucKhoe::where('NguoiDungID', $userId)->latest('ID')->first();

        return [
            'user_id' => $userId,
            'ten' => $profile->Ten ?? 'Bạn',
            'tuoi' => $age,
            'gioi_tinh' => $profile->GioiTinh ?? 'Không rõ',
            'sex' => mb_strtolower((string) ($profile->GioiTinh ?? 'nam')) === 'nam' ? 'M' : 'F',
            'weight' => $weight,
            'height_cm' => $heightCm,
            'bmi' => round($bmi, 1),
            'bmi_label' => $bmiLabel,
            'bmi_advice' => $bmiAdvice,
            'calo_nap' => round($caloriesIn),
            'calo_dot' => round($caloriesOut),
            'calo_balance' => round($balance),
            'calorie_context' => $this->calorieContext($caloriesIn, $caloriesOut, $balance),
            'protein' => round($protein, 1),
            'carb' => round($carb, 1),
            'fat' => round($fat, 1),
            'activity_minutes' => (int) $minutes,
            'steps' => (int) $steps,
            'meal_summary' => $mealSummary,
            'activity_summary' => $activitySummary,
            'health_score' => (int) ($scoreRecord->Diem ?? 70),
            'nutrition_advice' => $this->nutritionAdvice($caloriesIn, $protein, $carb, $fat),
            'nhom_mau' => $health->NhomMau ?? '',
            'benh_nen' => $health->BenhNen ?? '',
            'the_trang' => $health->TheTrang ?? '',
            'muc_do_van_dong' => $health->MucDoVanDong ?? '',
            'che_do_an' => $health->CheDoAn ?? '',
            'muc_tieu' => $this->goals($userId),
        ];
    }

    private function mealContext(int $userId, string $date): array
    {
        if (!Schema::hasTable('buaan')) {
            return [0, 0, 0, 0, 'Chưa ghi nhận bữa ăn hôm nay.'];
        }

        $meals = DB::table('buaan')
            ->where('NguoiDungID', $userId)
            ->whereDate('Ngay', $date)
            ->orderBy('ID')
            ->get();

        if ($meals->isEmpty()) {
            return [0, 0, 0, 0, 'Chưa ghi nhận bữa ăn hôm nay.'];
        }

        $calories = 0.0;
        $protein = 0.0;
        $carb = 0.0;
        $fat = 0.0;
        $lines = [];

        foreach ($meals as $meal) {
            $mealCalories = 0.0;
            $foods = [];

            if (Schema::hasTable('chitietbuaan')) {
                $details = DB::table('chitietbuaan')
                    ->leftJoin('thucpham', 'thucpham.ID', '=', 'chitietbuaan.ThucPhamID')
                    ->where('chitietbuaan.BuaAnID', $meal->ID)
                    ->get([
                        'chitietbuaan.SoLuong',
                        'chitietbuaan.TongCalo',
                        'chitietbuaan.TongProtein',
                        DB::raw(Schema::hasColumn('chitietbuaan', 'TongCarb') ? 'chitietbuaan.TongCarb' : '0 as TongCarb'),
                        DB::raw(Schema::hasColumn('chitietbuaan', 'TongFat') ? 'chitietbuaan.TongFat' : '0 as TongFat'),
                        DB::raw(Schema::hasTable('thucpham') ? 'thucpham.Ten as TenThucPham' : 'NULL as TenThucPham'),
                    ]);

                foreach ($details as $detail) {
                    $itemCalories = (float) ($detail->TongCalo ?? 0);
                    $calories += $itemCalories;
                    $protein += (float) ($detail->TongProtein ?? 0);
                    $carb += (float) ($detail->TongCarb ?? 0);
                    $fat += (float) ($detail->TongFat ?? 0);
                    $mealCalories += $itemCalories;
                    $foods[] = trim(($detail->TenThucPham ?? 'Món ăn') . ' ' . ($detail->SoLuong ? "({$detail->SoLuong}g)" : ''));
                }
            }

            $name = $meal->TenMonAn ?? $meal->LoaiBuaAn ?? 'Bữa ăn';
            $lines[] = '- ' . $name . (empty($foods) ? '' : ': ' . implode(', ', $foods)) . " ({$mealCalories} kcal)";
        }

        return [$calories, $protein, $carb, $fat, implode("\n", $lines)];
    }

    private function activityContext(int $userId, string $date): array
    {
        $calories = 0.0;
        $minutes = 0;
        $steps = 0;
        $lines = [];

        if (Schema::hasTable('activity_logs')) {
            $rows = DB::table('activity_logs')
                ->where('NguoiDungID', $userId)
                ->whereDate('NgayHoatDong', $date)
                ->orderBy('ID')
                ->get();

            foreach ($rows as $row) {
                $burned = (float) ($row->CaloriesDot ?? $row->CaloriesBurned ?? 0);
                $duration = (int) ($row->ThoiLuongPhut ?? 0);
                $calories += $burned;
                $minutes += $duration;
                $steps += (int) ($row->Steps ?? 0);
                $lines[] = "- " . ($row->TenHoatDong ?? 'Hoạt động') . ": {$duration} phút, {$burned} kcal";
            }
        } elseif (Schema::hasTable('lichhoatdong')) {
            $rows = DB::table('lichhoatdong')
                ->leftJoin('hoatdong', 'hoatdong.ID', '=', 'lichhoatdong.HoatDongID')
                ->leftJoin('chitiethoatdong', 'chitiethoatdong.LichHoatDongID', '=', 'lichhoatdong.ID')
                ->where('lichhoatdong.NguoiDungID', $userId)
                ->whereDate('lichhoatdong.ThoiGianBatDau', $date)
                ->get([
                    'hoatdong.TenHoatDong',
                    DB::raw('TIMESTAMPDIFF(MINUTE, lichhoatdong.ThoiGianBatDau, lichhoatdong.ThoiGianKetThuc) as ThoiLuongPhut'),
                    DB::raw('COALESCE(chitiethoatdong.CaloDot, hoatdong.Calo, 0) as CaloriesDot'),
                    DB::raw('COALESCE(chitiethoatdong.SoBuoc, 0) as Steps'),
                ]);

            foreach ($rows as $row) {
                $burned = (float) ($row->CaloriesDot ?? 0);
                $duration = (int) ($row->ThoiLuongPhut ?? 0);
                $calories += $burned;
                $minutes += $duration;
                $steps += (int) ($row->Steps ?? 0);
                $lines[] = "- " . ($row->TenHoatDong ?? 'Hoạt động') . ": {$duration} phút, {$burned} kcal";
            }
        }

        return [
            $calories,
            $minutes,
            $steps,
            empty($lines) ? 'Chưa ghi nhận hoạt động hôm nay.' : implode("\n", $lines),
        ];
    }

    private function goals(int $userId): string
    {
        if (!Schema::hasTable('muctieusuckhoe')) {
            return '';
        }

        return DB::table('muctieusuckhoe')
            ->where('NguoiDungID', $userId)
            ->pluck('TenMucTieu')
            ->filter()
            ->implode(', ');
    }

    private function bmiLabelAndAdvice(float $bmi): array
    {
        if ($bmi <= 0) return ['chưa có dữ liệu', 'cập nhật chiều cao và cân nặng để theo dõi chính xác hơn'];
        if ($bmi < 18.5) return ['thiếu cân', 'nên bổ sung protein, tinh bột tốt và theo dõi cân nặng'];
        if ($bmi <= 24.9) return ['bình thường', 'BMI đang ổn, duy trì nhịp ăn uống và vận động hiện tại'];
        if ($bmi <= 29.9) return ['thừa cân', 'nên kiểm soát khẩu phần và tăng vận động nhẹ'];
        return ['béo phì', 'nên ưu tiên món ít dầu, giảm nước ngọt và tăng vận động an toàn'];
    }

    private function calorieContext(float $in, float $out, float $balance): string
    {
        if ($in <= 0) return 'Chưa ghi nhận bữa ăn hôm nay.';
        if ($out <= 0) return "Đã nạp {$in} kcal nhưng chưa ghi nhận vận động.";
        if ($balance > 300) return "Nạp {$in} kcal, đốt {$out} kcal - đang dư {$balance} kcal.";
        if ($balance < -300) return "Nạp {$in} kcal, đốt {$out} kcal - đang thiếu " . abs($balance) . " kcal.";
        return "Nạp {$in} kcal, đốt {$out} kcal - khá cân bằng.";
    }

    private function nutritionAdvice(float $calories, float $protein, float $carb, float $fat): string
    {
        if ($calories <= 0) return 'Chưa có dữ liệu bữa ăn để đánh giá.';
        if ($protein < 50) return "Protein đang thấp ({$protein}g), nên thêm nguồn đạm đa dạng như cá, tôm, bò/thịt nạc, sữa chua ít đường, sữa đậu nành, đậu/đậu lăng hoặc các món bún/phở có thịt nạc.";
        if ($fat > 70) return "Chất béo hơi cao ({$fat}g), nên giảm món chiên/xào nhiều dầu.";
        if ($carb > 300) return "Carb hơi cao ({$carb}g), nên cân lại cơm/bánh mì/đồ ngọt.";
        return "Dinh dưỡng hôm nay khá ổn: {$calories} kcal, protein {$protein}g.";
    }
}
