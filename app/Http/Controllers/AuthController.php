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
use Illuminate\Support\Facades\Schema;

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

                'message' => 'Email Ä‘Ã£ tá»“n táº¡i',
            ], 409);
        }

        // CREATE ACCOUNT
        $accountPayload = [
            'Email' => $request->email,
            'MatKhauHash' => Hash::make($request->password),
            'TrangThaiHoatDong' => 1,
            'LanDangNhapCuoi' => now(),
            'NgayTao' => now(),
        ];
        if (Schema::hasColumn('taikhoan', 'is_blocked')) {
            $accountPayload['is_blocked'] = false;
        }

        $userId = DB::table('taikhoan')->insertGetId($accountPayload);

        // CREATE PROFILE
        DB::table('hosonguoidung')
            ->insert([

                'NguoiDungID' => $userId,

                'Ten' => 'NgÆ°á»i dÃ¹ng má»›i',

                'NgaySinh' => null,

                'GioiTinh' => null,

                'ChieuCao' => null,

                'AnhDaiDien' => '',
            ]);

        return response()->json([

            'success' => true,

            'message' => 'ÄÄƒng kÃ½ thÃ nh cÃ´ng',

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

                'message' => 'Email khÃ´ng tá»“n táº¡i',
            ], 404);
        }

        $isBlocked = (bool) ($user->is_blocked ?? false)
            || (int) ($user->TrangThaiHoatDong ?? 1) !== 1;

        if ($isBlocked) {
            return response()->json([
                'success' => false,
                'message' => 'Tài khoản của bạn đã bị khóa do vi phạm dữ liệu',
            ], 403);

            return response()->json([

                'success' => false,

                'message' => 'TÃ i khoáº£n Ä‘Ã£ bá»‹ khÃ³a. Vui lÃ²ng liÃªn há»‡ quáº£n trá»‹ viÃªn',
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

                'message' => 'Sai máº­t kháº©u',
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

            'message' => 'ÄÄƒng nháº­p thÃ nh cÃ´ng',

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
                'message' => 'Bạn thao tác quá nhiều lần. Vui lòng thử lại sau ' . RateLimiter::availableIn($rateKey) . ' giây.',
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
                    'message' => 'Email đã được xác minh. Vui lòng nhập mật khẩu mới.',
                    'requires_code' => true,
                    'debug_reset_code' => $code,
                    'test_mode' => true,
                ]);
            }

            try {
                Mail::raw(
                    "Mã đặt lại mật khẩu của bạn là: {$code}\n\nMã này có hiệu lực trong 10 phút. Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua email này.",
                    function ($message) use ($data) {
                        $message
                            ->to($data['email'])
                            ->subject('Mã đặt lại mật khẩu Salud');
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
                    'message' => 'Không thể gửi email đặt lại mật khẩu. Vui lòng thử lại sau.',
                ], 500);
            }

            $response = [
                'success' => true,
                'message' => 'Mã đặt lại mật khẩu đã được gửi đến email của bạn. Mã có hiệu lực trong 10 phút.',
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
