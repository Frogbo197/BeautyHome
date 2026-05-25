<?php

namespace App\Service;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class DailySummaryService
{
    public function calculate(int $userId, string $date): array
    {
        return [
            'CaloriesIn' => $this->caloriesIn($userId, $date),
            'CaloriesOut' => $this->caloriesOut($userId, $date),
            'WaterML' => $this->waterMl($userId, $date),
            'Steps' => $this->steps($userId, $date),
            'Protein' => $this->macro($userId, $date, 'TongProtein'),
            'Carb' => $this->macro($userId, $date, 'TongCarb'),
            'Fat' => $this->macro($userId, $date, 'TongFat'),
            'ThoiGianHoatDong' => $this->activityMinutes($userId, $date),
        ];
    }

    public function refresh(int $userId, string $date): array
    {
        $totals = $this->calculate($userId, $date);

        if (!Schema::hasTable('tomtatsuckhoehangngay')) {
            return $totals;
        }

        $payload = [
            'TongCaloVao' => $totals['CaloriesIn'],
            'TongCaloRa' => $totals['CaloriesOut'],
            'MucTieu' => $totals['CaloriesIn'] - $totals['CaloriesOut'],
            'TongLuongNuoc' => $totals['WaterML'],
            'TongBuocDi' => $totals['Steps'],
            'ThoiGianHoatDong' => $totals['ThoiGianHoatDong'],
            'TrangThaiHoanThanh' => $totals['CaloriesIn'] >= $totals['CaloriesOut'] ? 'balanced' : 'deficit',
            'NgayTao' => now(),
        ];

        foreach ([
            'TongProtein' => $totals['Protein'],
            'TongCarb' => $totals['Carb'],
            'TongChatBeo' => $totals['Fat'],
        ] as $column => $value) {
            if (Schema::hasColumn('tomtatsuckhoehangngay', $column)) {
                $payload[$column] = $value;
            }
        }

        DB::table('tomtatsuckhoehangngay')->updateOrInsert(
            ['NguoiDungID' => $userId, 'Ngay' => $date],
            $payload
        );

        return $totals;
    }

    private function caloriesIn(int $userId, string $date): float
    {
        if (!Schema::hasTable('buaan') || !Schema::hasTable('chitietbuaan')) {
            return 0;
        }

        if (Schema::hasColumn('chitietbuaan', 'TongCalo')) {
            return (float) DB::table('buaan')
                ->join('chitietbuaan', 'chitietbuaan.BuaAnID', '=', 'buaan.ID')
                ->where('buaan.NguoiDungID', $userId)
                ->whereDate('buaan.Ngay', $date)
                ->sum('chitietbuaan.TongCalo');
        }

        return 0;
    }

    private function macro(int $userId, string $date, string $column): float
    {
        if (!Schema::hasTable('buaan') || !Schema::hasTable('chitietbuaan')) {
            return 0;
        }
        if (!Schema::hasColumn('chitietbuaan', $column)) {
            return 0;
        }

        return (float) DB::table('buaan')
            ->join('chitietbuaan', 'chitietbuaan.BuaAnID', '=', 'buaan.ID')
            ->where('buaan.NguoiDungID', $userId)
            ->whereDate('buaan.Ngay', $date)
            ->sum("chitietbuaan.$column");
    }

    private function caloriesOut(int $userId, string $date): float
    {
        if (Schema::hasTable('activity_logs')) {
            $column = Schema::hasColumn('activity_logs', 'CaloriesDot') ? 'CaloriesDot' : 'CaloriesBurned';
            if (!Schema::hasColumn('activity_logs', $column)) {
                return 0;
            }

            return (float) DB::table('activity_logs')
                ->where('NguoiDungID', $userId)
                ->whereDate('NgayHoatDong', $date)
                ->sum($column);
        }

        if (Schema::hasTable('lichhoatdong') && Schema::hasTable('chitiethoatdong')) {
            return (float) DB::table('lichhoatdong')
                ->leftJoin('chitiethoatdong', 'chitiethoatdong.LichHoatDongID', '=', 'lichhoatdong.ID')
                ->where('lichhoatdong.NguoiDungID', $userId)
                ->whereDate('lichhoatdong.ThoiGianBatDau', $date)
                ->sum('chitiethoatdong.CaloDot');
        }

        return 0;
    }

    private function steps(int $userId, string $date): int
    {
        if (Schema::hasTable('activity_logs') && Schema::hasColumn('activity_logs', 'Steps')) {
            return (int) DB::table('activity_logs')
                ->where('NguoiDungID', $userId)
                ->whereDate('NgayHoatDong', $date)
                ->sum('Steps');
        }

        if (Schema::hasTable('lichhoatdong') && Schema::hasTable('chitiethoatdong')) {
            return (int) DB::table('lichhoatdong')
                ->leftJoin('chitiethoatdong', 'chitiethoatdong.LichHoatDongID', '=', 'lichhoatdong.ID')
                ->where('lichhoatdong.NguoiDungID', $userId)
                ->whereDate('lichhoatdong.ThoiGianBatDau', $date)
                ->sum('chitiethoatdong.SoBuoc');
        }

        return 0;
    }

    private function activityMinutes(int $userId, string $date): int
    {
        if (Schema::hasTable('activity_logs') && Schema::hasColumn('activity_logs', 'ThoiLuongPhut')) {
            return (int) DB::table('activity_logs')
                ->where('NguoiDungID', $userId)
                ->whereDate('NgayHoatDong', $date)
                ->sum('ThoiLuongPhut');
        }

        if (Schema::hasTable('lichhoatdong')) {
            return (int) (DB::table('lichhoatdong')
                ->where('NguoiDungID', $userId)
                ->whereDate('ThoiGianBatDau', $date)
                ->selectRaw('SUM(TIMESTAMPDIFF(MINUTE, ThoiGianBatDau, ThoiGianKetThuc)) as total')
                ->value('total') ?? 0);
        }

        return 0;
    }

    private function waterMl(int $userId, string $date): float
    {
        if (!Schema::hasTable('theodoinuoc')) {
            return 0;
        }

        return (float) DB::table('theodoinuoc')
            ->where('NguoiDungID', $userId)
            ->whereDate('Ngay', $date)
            ->sum('LuongNuoc');
    }
}
