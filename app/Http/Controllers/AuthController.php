<?php

namespace App\Http\Controllers;

use App\Service\AuthTokenService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

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
        try {

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
        $storedPassword = (string) ($user->MatKhauHash ?? '');
        $passwordValid = false;
        $rehashPassword = false;

        try {
            $passwordValid = Hash::check($request->password, $storedPassword);
            $rehashPassword = $passwordValid && Hash::needsRehash($storedPassword);
        } catch (\Throwable) {
            $passwordValid = false;
        }

        if (!$passwordValid && hash_equals($storedPassword, (string) $request->password)) {
            $passwordValid = true;
            $rehashPassword = true;
        }

        if (!$passwordValid) {

            return response()->json([

                'success' => false,

                'message' => 'Sai mật khẩu',
            ], 401);
        }

        // UPDATE LOGIN TIME
        $loginUpdate = [
            'LanDangNhapCuoi' => now(),
        ];
        if ($rehashPassword) {
            $loginUpdate['MatKhauHash'] = Hash::make($request->password);
        }

        DB::table('taikhoan')
            ->where('ID', $user->ID)
            ->update($loginUpdate);

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
        } catch (\Throwable $exception) {
            Log::error('Login failed with server error', [
                'email' => $request->input('email'),
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Loi may chu dang nhap: ' . $exception->getMessage(),
            ], 500);
        }
    }

    public function forgotPassword(Request $request)
    {
        $data = $request->validate([
            'email' => 'required|email',
            'reset_code' => 'nullable|string|size:6',
            'password' => 'nullable|required_with:reset_code|string|min:6|confirmed',
        ]);

        $normalizedEmail = strtolower($data['email']);
        $rateKey = 'forgot-password:' . $normalizedEmail . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($rateKey, 5)) {
            return response()->json([
                'success' => false,
                'message' => 'Ban thao tac qua nhieu lan. Vui long thu lai sau ' . RateLimiter::availableIn($rateKey) . ' giay.',
            ], 429);
        }

        RateLimiter::hit($rateKey, 60);

        $user = DB::table('taikhoan')
            ->where('Email', $data['email'])
            ->first();

        if (!$user) {
            return response()->json([
                'success' => true,
                'message' => 'Nếu email tồn tại, mã đặt lại mật khẩu đã được tạo.',
            ]);
        }

        $cacheKey = 'password_reset:' . $normalizedEmail;

        if (empty($data['reset_code'])) {
            $code = (string) random_int(100000, 999999);
            Cache::put($cacheKey, Hash::make($code), now()->addMinutes(10));
            $testMode = filter_var(env('PASSWORD_RESET_TEST_MODE', false), FILTER_VALIDATE_BOOLEAN);

            if ($testMode) {
                return response()->json([
                    'success' => true,
                    'message' => 'Email da duoc xac minh. Vui long nhap mat khau moi.',
                    'requires_code' => true,
                    'debug_reset_code' => $code,
                    'test_mode' => true,
                ]);
            }

            try {
                Mail::raw(
                    "Ma dat lai mat khau cua ban la: {$code}\n\nMa nay co hieu luc trong 10 phut. Neu ban khong yeu cau dat lai mat khau, hay bo qua email nay.",
                    function ($message) use ($data) {
                        $message
                            ->to($data['email'])
                            ->subject('Ma dat lai mat khau Salud');
                    }
                );
            } catch (\Throwable $exception) {
                Cache::forget($cacheKey);
                Log::error('Failed to send password reset email', [
                    'email' => $data['email'],
                    'message' => $exception->getMessage(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Khong the gui email dat lai mat khau. Vui long thu lai sau.',
                ], 500);
            }

            $response = [
                'success' => true,
                'message' => 'Ma dat lai mat khau da duoc gui den email cua ban. Ma co hieu luc trong 10 phut.',
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
