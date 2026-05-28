<?php

namespace App\Service\HealthAnalysis;

use App\DTO\HealthAnalysisResultDTO;
use App\DTO\HealthContextDTO;
use App\Models\DailyHealthSummary;
use App\Models\DiemSucKhoe;
use App\Models\GoiYDinhDuong;
use App\Models\GoiYLuyenTap;
use App\Models\PhanTichSucKhoeAI;
use Illuminate\Support\Facades\DB;

class HealthAnalysisPersistenceService
{
    public function save(HealthContextDTO $context, HealthAnalysisResultDTO $result, string $prompt, string $modelVersion): void
    {
        DB::transaction(function () use ($context, $result, $prompt, $modelVersion) {
            DiemSucKhoe::create([
                'NguoiDungID' => $context->userId,
                'Diem' => $result->healthScore,
                'NgayTinh' => now(),
                'NhanXetAI' => $result->result,
            ]);

            GoiYDinhDuong::where('NguoiDungID', $context->userId)->delete();
            GoiYLuyenTap::where('NguoiDungID', $context->userId)->delete();

            foreach ($result->nutritionRecommendations as $food) {
                GoiYDinhDuong::create([
                    'NguoiDungID' => $context->userId,
                    'MonAn' => $food,
                    'LoaiBuaAn' => 'Khuyen nghi AI',
                    'Ngay' => now(),
                ]);
            }

            foreach ($result->workoutRecommendations as $workout) {
                GoiYLuyenTap::create([
                    'NguoiDungID' => $context->userId,
                    'TenBaiTap' => $workout,
                    'ThoiLuong' => 30,
                    'CaloDot' => 200,
                    'NgayTao' => now(),
                    'RecommendationScore' => 90,
                    'DifficultyLevel' => 'Trung binh',
                    'GoalType' => optional($context->preferences)->MucTieu ?? 'General',
                    'GeneratedReason' => 'AI recommendation',
                    'SourceType' => 'AI',
                    'ModelVersion' => $modelVersion,
                ]);
            }

            PhanTichSucKhoeAI::create([
                'NguoiDungID' => $context->userId,
                'LoaiPhanTich' => 'TongQuat',
                'KetQua' => $result->result,
                'prompt' => $prompt,
                'Model' => $modelVersion,
            ]);

            DailyHealthSummary::updateOrCreate(
                [
                    'NguoiDungID' => $context->userId,
                    'Ngay' => now()->toDateString(),
                ],
                [
                    'TongCaloVao' => $result->caloriesIn,
                    'TongCaloRa' => $result->caloriesOut,
                    'MucTieu' => $result->calorieBalance,
                    'TongProtein' => $result->protein,
                    'TongCarb' => $result->carb,
                    'TongChatBeo' => $result->fat,
                    'TongLuongNuoc' => 2000,
                    'TongBuocDi' => $result->steps,
                    'ThoiGianHoatDong' => $result->activityMinutes,
                    'DiemSucKhoe' => $result->healthScore,
                    'AIPhanTich' => $result->result,
                    'TrangThaiHoanThanh' => $result->healthStatus,
                ]
            );
        });
    }
}
