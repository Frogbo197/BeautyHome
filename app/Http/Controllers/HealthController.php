<?php

namespace App\Http\Controllers;

use App\Service\OpenRouterService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class HealthController extends Controller
{
    public function analyze(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = DB::table('taikhoan')
            ->where('Email', $request->email)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found',
            ], 404);
        }

        $profile = DB::table('hosonguoidung')
            ->where('NguoiDungID', $user->ID)
            ->first();

        if (!$profile) {
            return response()->json([
                'success' => false,
                'message' => 'Onboarding profile not found',
            ], 404);
        }

        $health = DB::table('hososuckhoe')
            ->where('NguoiDungID', $user->ID)
            ->first();

        $goals = DB::table('muctieusuckhoe')
            ->where('NguoiDungID', $user->ID)
            ->pluck('TenMucTieu')
            ->filter()
            ->values()
            ->all();

        $weight = (float) ($profile->CanNang ?? 0);
        $height = max((float) ($profile->ChieuCao ?? 170), 1);
        $fitness = is_numeric($health->TheTrang ?? null)
            ? (int) $health->TheTrang
            : 0;

        $bmi = $weight > 0
            ? $weight / (($height / 100) * ($height / 100))
            : 0;

        $score = 100;
        $warnings = [];
        $suggestions = [];

        if ($bmi > 0 && $bmi < 18.5) {
            $score -= 10;
            $warnings[] = 'Underweight';
            $suggestions[] = 'Add more nutritious meals';
        } elseif ($bmi > 25) {
            $score -= 15;
            $warnings[] = 'Overweight';
            $suggestions[] = 'Reduce refined carbohydrates';
        }

        if ($fitness > 0 && $fitness < 2) {
            $score -= 10;
            $warnings[] = 'Low activity level';
            $suggestions[] = 'Start with light cardio';
        }

        foreach ($goals as $goal) {
            if (str_contains(mb_strtolower($goal), 'giam')) {
                $suggestions[] = 'Walk 30 minutes per day';
            }
        }

        $score = max(0, min(100, $score));
        $summary = $score >= 80 ? 'Good health' : 'Needs improvement';

        $prompt = implode("\n", [
            'You are a Vietnamese health assistant.',
            'Give short, practical advice in Vietnamese.',
            "BMI: {$bmi}",
            "Fitness: {$fitness}",
            'Warnings: '.implode(', ', $warnings),
            'Suggestions: '.implode(', ', $suggestions),
            'Goals: '.implode(', ', $goals),
        ]);

        $aiResult = $this->localAdvice($score, $warnings, $suggestions);

        try {
            $aiResult = app(OpenRouterService::class)->ask($prompt);
        } catch (\Throwable $exception) {
            Log::warning('Health AI fallback used', [
                'message' => $exception->getMessage(),
            ]);
        }

        DB::table('diemsuckhoe')->insert([
            'NguoiDungID' => $user->ID,
            'Diem' => $score,
            'NhanXetAI' => $aiResult,
            'NgayTinh' => now()->toDateString(),
        ]);

        return response()->json([
            'success' => true,
            'score' => $score,
            'warnings' => $warnings,
            'suggestions' => $suggestions,
            'summary' => $summary,
            'ai' => $aiResult,
        ]);
    }

    public function saveOnboarding(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'gender' => 'nullable|string|max:20',
            'weight' => 'nullable|numeric|min:0',
            'height' => 'nullable|numeric|min:0',
            'age' => 'nullable|integer|min:0|max:130',
            'blood' => 'nullable|string|max:20',
            'fitness' => 'nullable',
            'goal' => 'nullable|array',
            'heartRate' => 'nullable|integer|min:0',
            'systolic' => 'nullable|integer|min:0',
            'diastolic' => 'nullable|integer|min:0',
        ]);

        $user = DB::table('taikhoan')
            ->where('Email', $validated['email'])
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Account not found',
            ], 404);
        }

        DB::transaction(function () use ($request, $validated, $user) {
            $birthday = isset($validated['age'])
                ? now()->subYears((int) $validated['age'])->toDateString()
                : null;

            DB::table('hosonguoidung')->updateOrInsert(
                ['NguoiDungID' => $user->ID],
                [
                    'NgaySinh' => $birthday,
                    'GioiTinh' => $validated['gender'] ?? null,
                    'ChieuCao' => $validated['height'] ?? null,
                    'CanNang' => $validated['weight'] ?? null,
                    'NgayCapNhat' => now(),
                ]
            );

            DB::table('hososuckhoe')->updateOrInsert(
                ['NguoiDungID' => $user->ID],
                [
                    'NhomMau' => $validated['blood'] ?? null,
                    'TheTrang' => $request->input('fitness'),
                    'NgayCapNhat' => now(),
                ]
            );

            $height = max((float) ($validated['height'] ?? 0), 1);
            $weight = (float) ($validated['weight'] ?? 0);
            $bmi = $weight > 0
                ? $weight / (($height / 100) * ($height / 100))
                : null;

            DB::table('chisosuckhoe')->insert([
                'NguoiDungID' => $user->ID,
                'Ngay' => now()->toDateString(),
                'CanNang' => $validated['weight'] ?? null,
                'HuyetAp' => $this->bloodPressure($request),
                'NhipTim' => $validated['heartRate'] ?? null,
                'BMI' => $bmi,
            ]);

            DB::table('muctieusuckhoe')
                ->where('NguoiDungID', $user->ID)
                ->delete();

            foreach (($validated['goal'] ?? []) as $goal) {
                DB::table('muctieusuckhoe')->insert([
                    'NguoiDungID' => $user->ID,
                    'TenMucTieu' => $goal,
                    'NgayBatDau' => now()->toDateString(),
                    'TrangThai' => 'active',
                    'LoaiMucTieu' => 'general',
                ]);
            }
        });

        return response()->json(['success' => true]);
    }

    public function weight(Request $request)
    {
        $validated = $request->validate([
            'NguoiDungID' => 'required|integer|exists:taikhoan,ID',
            'range' => 'nullable|in:week,month,year',
            'from' => 'nullable|date',
            'to' => 'nullable|date',
        ]);

        $range = $validated['range'] ?? 'month';
        $now = now('Asia/Ho_Chi_Minh');
        $from = $validated['from'] ?? match ($range) {
            'week' => $now->copy()->startOfWeek()->toDateString(),
            'year' => $now->copy()->startOfYear()->toDateString(),
            default => $now->copy()->startOfMonth()->toDateString(),
        };
        $to = $validated['to'] ?? match ($range) {
            'week' => $now->copy()->endOfWeek()->toDateString(),
            'year' => $now->copy()->endOfYear()->toDateString(),
            default => $now->copy()->endOfMonth()->toDateString(),
        };

        $columns = [
            'ID',
            'NguoiDungID',
            'Ngay',
            'CanNang',
            'HuyetAp',
            'NhipTim',
            'BMI',
        ];
        if (Schema::hasColumn('chisosuckhoe', 'HinhAnh')) {
            $columns[] = 'HinhAnh';
        }

        $rows = DB::table('chisosuckhoe')
            ->where('NguoiDungID', $validated['NguoiDungID'])
            ->whereNotNull('CanNang')
            ->whereDate('Ngay', '>=', $from)
            ->whereDate('Ngay', '<=', $to)
            ->orderBy('Ngay')
            ->orderBy('ID')
            ->get($columns);

        if ($rows->isEmpty() && Schema::hasTable('hosonguoidung')) {
            $profile = DB::table('hosonguoidung')
                ->where('NguoiDungID', $validated['NguoiDungID'])
                ->first();
            $profileWeight = (float) ($profile->CanNang ?? 0);
            if ($profileWeight > 0) {
                $heightM = !empty($profile->ChieuCao) ? ((float) $profile->ChieuCao) / 100 : 0;
                $rows->push((object) [
                    'ID' => 0,
                    'NguoiDungID' => (int) $validated['NguoiDungID'],
                    'Ngay' => now('Asia/Ho_Chi_Minh')->toDateString(),
                    'CanNang' => $profileWeight,
                    'HuyetAp' => null,
                    'NhipTim' => null,
                    'BMI' => $heightM > 0 ? round($profileWeight / ($heightM * $heightM), 2) : null,
                    'source' => 'profile',
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'range' => $range,
            'from' => $from,
            'to' => $to,
            'data' => $rows,
        ]);
    }

    public function storeWeight(Request $request)
    {
        $validated = $request->validate([
            'NguoiDungID' => 'required|integer|exists:taikhoan,ID',
            'Ngay' => 'nullable|date',
            'CanNang' => 'required|numeric|min:1|max:500',
            'HinhAnh' => 'nullable|string|max:500',
        ]);

        $userId = (int) $validated['NguoiDungID'];
        $date = $validated['Ngay'] ?? now('Asia/Ho_Chi_Minh')->toDateString();
        $weight = (float) $validated['CanNang'];
        $previousWeight = Schema::hasTable('hosonguoidung')
            ? DB::table('hosonguoidung')->where('NguoiDungID', $userId)->value('CanNang')
            : null;
        if (!$previousWeight) {
            $previousWeight = DB::table('chisosuckhoe')
                ->where('NguoiDungID', $userId)
                ->whereNotNull('CanNang')
                ->orderByDesc('Ngay')
                ->orderByDesc('ID')
                ->value('CanNang');
        }
        $height = DB::table('hosonguoidung')
            ->where('NguoiDungID', $userId)
            ->value('ChieuCao');
        $heightM = $height ? ((float) $height) / 100 : 0;
        $bmi = $heightM > 0 ? round($weight / ($heightM * $heightM), 2) : null;

        $payload = [
            'CanNang' => $weight,
            'BMI' => $bmi,
        ];
        if (Schema::hasColumn('chisosuckhoe', 'HinhAnh') && array_key_exists('HinhAnh', $validated)) {
            $payload['HinhAnh'] = $validated['HinhAnh'];
        }

        DB::table('chisosuckhoe')->insert(array_merge($payload, [
            'NguoiDungID' => $userId,
            'Ngay' => $date,
        ]));

        if (Schema::hasTable('hosonguoidung')) {
            DB::table('hosonguoidung')
                ->where('NguoiDungID', $userId)
                ->update(['CanNang' => $weight, 'NgayCapNhat' => now()]);
        }

        $row = DB::table('chisosuckhoe')
            ->where('NguoiDungID', $userId)
            ->whereDate('Ngay', $date)
            ->orderByDesc('ID')
            ->first();

        return response()->json([
            'success' => true,
            'data' => $row,
            'previous_weight' => $previousWeight !== null ? (float) $previousWeight : null,
            'delta' => $previousWeight !== null ? round($weight - (float) $previousWeight, 2) : 0,
            'trend' => $this->weightTrend($previousWeight !== null ? $weight - (float) $previousWeight : 0),
        ], 201);
    }

    private function weightTrend(float $delta): string
    {
        if ($delta > 0.1) {
            return 'up';
        }
        if ($delta < -0.1) {
            return 'down';
        }
        return 'stable';
    }

    private function bloodPressure(Request $request): ?string
    {
        if (!$request->filled('systolic') && !$request->filled('diastolic')) {
            return null;
        }

        return $request->input('systolic', 0).'/'.$request->input('diastolic', 0);
    }

    private function localAdvice(int $score, array $warnings, array $suggestions): string
    {
        $status = $score >= 80 ? 'suc khoe dang on dinh' : 'can cai thien mot vai thoi quen';
        $nextStep = $suggestions[0] ?? 'duy tri an uong can bang, uong du nuoc va van dong nhe moi ngay';

        if ($warnings) {
            return 'Danh gia nhanh: '.$status.'. Luu y: '.implode(', ', $warnings).'. Goi y: '.$nextStep.'.';
        }

        return 'Danh gia nhanh: '.$status.'. Goi y: '.$nextStep.'.';
    }
}
