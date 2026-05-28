<?php

namespace App\Http\Controllers;

use App\Service\HealthScoreService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class UserController extends Controller
{
    public function saveUser(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'name'  => 'nullable|string',
        ]);

        $user = DB::table('taikhoan')
            ->where('Email', $request->email)
            ->first();

        if (!$user) {
            DB::table('taikhoan')->insert([
                'Email' => $request->email,
                'MatKhauHash' => '',
                'TrangThaiHoatDong' => 1,
                'NgayTao' => now(),
            ]);
        }

        return response()->json(['status' => 'ok']);
    }

    public function saveOnboarding(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        if (!$data || !isset($data['email'])) {
            return response()->json([
                'success' => false,
                'message' => 'Body rỗng hoặc sai định dạng',
            ], 400);
        }

        $user = DB::table('taikhoan')
            ->where('Email', $data['email'])
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy người dùng',
            ], 404);
        }

        $authUserId = (int) $request->attributes->get('auth_user_id', 0);
        if ($authUserId > 0 && (int) $user->ID !== $authUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền cập nhật hồ sơ người dùng này',
            ], 403);
        }

        $heightCm = $this->nullableFloat($data['chieu_cao'] ?? null);
        $weightKg = $this->nullableFloat($data['can_nang'] ?? null);
        $bmi = null;

        if ($heightCm && $weightKg) {
            $heightM = $heightCm / 100;
            $bmi = round($weightKg / ($heightM * $heightM), 1);
        }

        DB::table('hosonguoidung')->updateOrInsert(
            ['NguoiDungID' => $user->ID],
            [
                'Ten' => $data['ten'] ?? 'Nguoi dung moi',
                'GioiTinh' => $data['gioi_tinh'] ?? null,
                'NgaySinh' => $data['ngay_sinh'] ?? null,
                'ChieuCao' => $heightCm,
                'CanNang' => $weightKg,
                'AnhDaiDien' => $data['anh_dai_dien'] ?? '',
                'NgayCapNhat' => now(),
            ]
        );

        DB::table('hososuckhoe')->updateOrInsert(
            ['NguoiDungID' => $user->ID],
            [
                'NhomMau' => $data['nhom_mau'] ?? null,
                'BenhNen' => $data['benh_nen'] ?? null,
                'TheTrang' => $data['the_trang'] ?? null,
                'NgayCapNhat' => now(),
            ]
        );

        DB::table('chisosuckhoe')->updateOrInsert(
            [
                'NguoiDungID' => $user->ID,
                'Ngay' => now('Asia/Ho_Chi_Minh')->toDateString(),
            ],
            [
                'CanNang' => $weightKg,
                'BMI' => $bmi,
                'HuyetAp' => $data['huyet_ap'] ?? null,
                'NhipTim' => isset($data['nhip_tim']) ? (int) $data['nhip_tim'] : null,
            ]
        );

        $goals = $data['muc_tieu'] ?? [];
        DB::table('muctieusuckhoe')
            ->where('NguoiDungID', $user->ID)
            ->delete();

        foreach ($goals as $goal) {
            DB::table('muctieusuckhoe')->insert([
                'NguoiDungID' => $user->ID,
                'TenMucTieu' => $goal,
            ]);
        }

        if (
            array_key_exists('muc_do_van_dong', $data) ||
            array_key_exists('che_do_an', $data)
        ) {
            DB::table('sothichnguoidung')->updateOrInsert(
                ['NguoiDung' => $user->ID],
                [
                    'MucTieu' => json_encode($goals, JSON_UNESCAPED_UNICODE),
                    'MucDoVanDong' => $data['muc_do_van_dong'] ?? null,
                    'CheDoAn' => $data['che_do_an'] ?? null,
                ]
            );
        }

        return response()->json([
            'success' => true,
            'user_id' => $user->ID,
            'bmi' => $bmi,
            'message' => 'Lưu onboarding thành công',
        ]);
    }

    public function analyzeHealth(Request $request, HealthScoreService $healthScoreService)
    {
        $data = json_decode($request->getContent(), true);
        $userId = $data['user_id'] ?? null;

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Thiếu user_id',
            ], 400);
        }

        $user = DB::table('hosonguoidung')
            ->where('NguoiDungID', $userId)
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy hồ sơ ID: ' . $userId,
            ], 404);
        }

        $analysis = $healthScoreService->calculateForUser((int) $userId);

        DB::table('diemsuckhoe')->insert([
            'NguoiDungID' => $userId,
            'Diem' => $analysis['score'],
            'NhanXetAI' => $analysis['summary'],
            'NgayTinh' => now('Asia/Ho_Chi_Minh')->toDateString(),
        ]);

        DB::table('phantichsuckhoeai')->insert([
            'NguoiDungID' => $userId,
            'LoaiPhanTich' => 'onboarding_health_score',
            'LoaiNguon' => 'RuleBased',
            'KetQua' => json_encode($analysis, JSON_UNESCAPED_UNICODE),
            'DoTinCay' => 0.85,
            'MoHinh' => 'rule-based-health-score-v1',
            'ThoiGianXuLy' => 0,
            'NgayPhanTich' => now('Asia/Ho_Chi_Minh')->toDateString(),
            'DuLieuDauVao' => json_encode($analysis['input_snapshot'], JSON_UNESCAPED_UNICODE),
            'prompt' => 'WHO BMI + WHO physical activity + AHA blood pressure rule-based scoring',
            'Model' => 'rules-v1',
        ]);

        return response()->json([
            'success' => true,
            'score' => $analysis['score'],
            'status' => $analysis['status'],
            'advice' => $analysis['advice'],
            'bmi' => $analysis['bmi'],
            'bmi_category' => $analysis['bmi_category'],
            'components' => $analysis['components'],
            'missing_data' => $analysis['missing_data'],
            'summary' => $analysis['summary'],
            'user' => [
                'Ten' => $user->Ten,
                'AnhDaiDien' => $user->AnhDaiDien,
            ],
        ]);
    }

    public function getUsers(Request $request)
    {
        if ($request->email) {
            return DB::table('taikhoan')
                ->where('Email', $request->email)
                ->first();
        }

        return DB::table('taikhoan')->get();
    }

    public function getProfile($userId)
    {
        $user = DB::table('taikhoan')->where('ID', $userId)->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy người dùng',
            ], 404);
        }

        $profile = DB::table('hosonguoidung')
            ->where('NguoiDungID', $userId)
            ->first();

        $health = DB::table('hososuckhoe')
            ->where('NguoiDungID', $userId)
            ->first();

        $preferences = DB::table('sothichnguoidung')
            ->where('NguoiDung', $userId)
            ->first();

        $goals = DB::table('muctieusuckhoe')
            ->where('NguoiDungID', $userId)
            ->pluck('TenMucTieu')
            ->filter()
            ->values();

        return response()->json([
            'success' => true,
            'user_id' => (int) $userId,
            'email' => $user->Email,
            'ten' => $profile->Ten ?? '',
            'ngay_sinh' => $profile->NgaySinh ?? null,
            'gioi_tinh' => $profile->GioiTinh ?? null,
            'chieu_cao' => $profile->ChieuCao ?? null,
            'can_nang' => $profile->CanNang ?? null,
            'anh_dai_dien' => $profile->AnhDaiDien ?? '',
            'nhom_mau' => $health->NhomMau ?? null,
            'benh_nen' => $health->BenhNen ?? null,
            'the_trang' => $health->TheTrang ?? null,
            'muc_do_van_dong' => $preferences->MucDoVanDong ?? null,
            'che_do_an' => $preferences->CheDoAn ?? null,
            'muc_tieu' => $goals,
        ]);
    }

    public function updateProfile(Request $request, HealthScoreService $healthScoreService)
    {
        $data = $request->json()->all();

        $user = null;
        if (!empty($data['user_id'])) {
            $user = DB::table('taikhoan')->where('ID', $data['user_id'])->first();
        }
        if (!$user && !empty($data['email'])) {
            $user = DB::table('taikhoan')->where('Email', $data['email'])->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Không tìm thấy người dùng',
            ], 404);
        }

        $authUserId = (int) $request->attributes->get('auth_user_id', 0);
        if ($authUserId > 0 && (int) $user->ID !== $authUserId) {
            return response()->json([
                'success' => false,
                'message' => 'Không có quyền cập nhật hồ sơ người dùng này',
            ], 403);
        }

        $heightCm = $this->nullableFloat($data['chieu_cao'] ?? null);
        $weightKg = $this->nullableFloat($data['can_nang'] ?? null);
        $bmi = null;
        if ($heightCm && $weightKg) {
            $heightM = $heightCm / 100;
            $bmi = round($weightKg / ($heightM * $heightM), 1);
        }

        DB::table('hosonguoidung')->updateOrInsert(
            ['NguoiDungID' => $user->ID],
            [
                'Ten' => $data['ten'] ?? '',
                'NgaySinh' => $data['ngay_sinh'] ?? null,
                'GioiTinh' => $data['gioi_tinh'] ?? null,
                'ChieuCao' => $heightCm,
                'CanNang' => $weightKg,
                'AnhDaiDien' => $data['anh_dai_dien'] ?? '',
                'NgayCapNhat' => now(),
            ]
        );

        DB::table('hososuckhoe')->updateOrInsert(
            ['NguoiDungID' => $user->ID],
            [
                'NhomMau' => $data['nhom_mau'] ?? null,
                'BenhNen' => $data['benh_nen'] ?? null,
                'TheTrang' => $data['the_trang'] ?? null,
                'NgayCapNhat' => now(),
            ]
        );

        DB::table('chisosuckhoe')->updateOrInsert(
            [
                'NguoiDungID' => $user->ID,
                'Ngay' => now('Asia/Ho_Chi_Minh')->toDateString(),
            ],
            [
                'CanNang' => $weightKg,
                'BMI' => $bmi,
                'HuyetAp' => $data['huyet_ap'] ?? null,
                'NhipTim' => isset($data['nhip_tim']) ? (int) $data['nhip_tim'] : null,
            ]
        );

        if (
            array_key_exists('muc_do_van_dong', $data) ||
            array_key_exists('che_do_an', $data) ||
            array_key_exists('muc_tieu', $data)
        ) {
            DB::table('sothichnguoidung')->updateOrInsert(
                ['NguoiDung' => $user->ID],
                [
                    'MucTieu' => json_encode($data['muc_tieu'] ?? [], JSON_UNESCAPED_UNICODE),
                    'MucDoVanDong' => $data['muc_do_van_dong'] ?? null,
                    'CheDoAn' => $data['che_do_an'] ?? null,
                ]
            );
        }

        $analysis = $healthScoreService->calculateForUser((int) $user->ID);
        DB::table('diemsuckhoe')->insert([
            'NguoiDungID' => $user->ID,
            'Diem' => $analysis['score'],
            'NhanXetAI' => $analysis['summary'],
            'NgayTinh' => now('Asia/Ho_Chi_Minh')->toDateString(),
        ]);

        DB::table('phantichsuckhoeai')->insert([
            'NguoiDungID' => $user->ID,
            'LoaiPhanTich' => 'profile_health_score',
            'LoaiNguon' => 'RuleBased',
            'KetQua' => json_encode($analysis, JSON_UNESCAPED_UNICODE),
            'DoTinCay' => 0.85,
            'MoHinh' => 'rule-based-health-score-v1',
            'ThoiGianXuLy' => 0,
            'NgayPhanTich' => now('Asia/Ho_Chi_Minh')->toDateString(),
            'DuLieuDauVao' => json_encode($analysis['input_snapshot'], JSON_UNESCAPED_UNICODE),
            'prompt' => 'Recalculate health score after profile update',
            'Model' => 'rules-v1',
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Cập nhật hồ sơ thành công',
            'score' => $analysis['score'],
            'bmi' => $analysis['bmi'],
        ]);
    }

    private function nullableFloat($value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (float) $value;
    }
}
