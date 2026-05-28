<?php

namespace App\Http\Controllers;

use App\Service\AuthTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function __construct(private readonly AuthTokenService $tokens)
    {
    }

    // =====================================================
    // REGISTER
    // =====================================================

    public function register(Request $request)
    {

        $request->validate([

            'email' => 'required|email',

            'password' => 'required|min:6',
        ]);

        // CHECK EMAIL EXIST
        $exists = DB::table('taikhoan')
            ->where('Email', $request->email)
            ->first();

        if ($exists) {

            return response()->json([

                'success' => false,

                'message' => 'Email đã tồn tại',
            ], 409);
        }

        // CREATE ACCOUNT
        $userId = DB::table('taikhoan')
            ->insertGetId([

                'Email' => $request->email,

                'MatKhauHash' =>
                    Hash::make($request->password),

                'TrangThaiHoatDong' => 1,

                'LanDangNhapCuoi' => now(),

                'NgayTao' => now(),
            ]);

        // CREATE PROFILE
        DB::table('hosonguoidung')
            ->insert([

                'NguoiDungID' => $userId,

                'Ten' => 'Người dùng mới',

                'NgaySinh' => null,

                'GioiTinh' => null,

                'ChieuCao' => null,

                'AnhDaiDien' => '',
            ]);

        return response()->json([

            'success' => true,

            'message' => 'Đăng ký thành công',

            'user_id' => $userId,

            'token' => $this->tokens->issue((int) $userId, $request->email),
        ]);
    }

    // =====================================================
    // LOGIN
    // =====================================================

    public function login(Request $request)
    {

        $request->validate([

            'email' => 'required|email',

            'password' => 'required',
        ]);

        // FIND ACCOUNT
        $user = DB::table('taikhoan')
            ->where('Email', $request->email)
            ->first();

        if (!$user) {

            return response()->json([

                'success' => false,

                'message' => 'Email không tồn tại',
            ], 404);
        }

        if ((int) ($user->TrangThaiHoatDong ?? 1) !== 1) {

            return response()->json([

                'success' => false,

                'message' => 'Tài khoản đã bị khóa. Vui lòng liên hệ quản trị viên',
            ], 403);
        }

        // CHECK PASSWORD
        if (!Hash::check(
            $request->password,
            $user->MatKhauHash
        )) {

            return response()->json([

                'success' => false,

                'message' => 'Sai mật khẩu',
            ], 401);
        }

        // UPDATE LOGIN TIME
        DB::table('taikhoan')
            ->where('ID', $user->ID)
            ->update([

                'LanDangNhapCuoi' => now(),
            ]);

        // GET PROFILE
        $profile = DB::table('hosonguoidung')
            ->where('NguoiDungID', $user->ID)
            ->first();

        return response()->json([

            'success' => true,

            'message' => 'Đăng nhập thành công',

            'token' => $this->tokens->issue((int) $user->ID, $user->Email),

            'user' => [

                'ID' => $user->ID,

                'Email' => $user->Email,

                'Ten' =>
                    $profile->Ten ?? '',

                'NgaySinh' =>
                    $profile->NgaySinh ?? null,

                'GioiTinh' =>
                    $profile->GioiTinh ?? '',

                'ChieuCao' =>
                    $profile->ChieuCao ?? 0,

                'AnhDaiDien' =>
                    $profile->AnhDaiDien ?? '',
            ]
        ]);
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'reset_code' => 'nullable|string|size:6',
            'password' => 'nullable|required_with:reset_code|string|min:6|confirmed',
        ]);

        $user = DB::table('taikhoan')
            ->where('Email', $data['email'])
            ->first();

        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'Nếu email tồn tại, mã đặt lại mật khẩu đã được tạo.',
            ]);
        }

        $cacheKey = 'password_reset:' . strtolower($data['email']);

        if (empty($data['reset_code'])) {
            $code = (string) random_int(100000, 999999);
            Cache::put($cacheKey, Hash::make($code), now()->addMinutes(10));

            $response = [
                'success' => true,
                'message' => 'Mã đặt lại mật khẩu có hiệu lực trong 10 phút.',
                'requires_code' => true,
            ];

            if (app()->environment('local')) {
                $response['debug_reset_code'] = $code;
            }

            return response()->json($response);
        }

        $storedHash = Cache::get($cacheKey);
        if (!$storedHash || !Hash::check($data['reset_code'], $storedHash)) {
            return response()->json([
                'success' => false,
                'message' => 'Mã đặt lại mật khẩu không đúng hoặc đã hết hạn.',
            ], 422);
        }

        DB::table('taikhoan')
            ->where('ID', $user->ID)
            ->update([
                'MatKhauHash' => Hash::make($data['password']),
            ]);
        Cache::forget($cacheKey);

        return response()->json([
            'success' => true,
            'message' => 'Đã cập nhật mật khẩu. Bạn có thể đăng nhập lại.',
        ]);
    }
}
