<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{

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
            'password' => 'required|string|min:6|confirmed',
        ]);

        $user = DB::table('taikhoan')
            ->where('Email', $data['email'])
            ->first();

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Email khong ton tai',
            ], 404);
        }

        DB::table('taikhoan')
            ->where('ID', $user->ID)
            ->update([
                'MatKhauHash' => Hash::make($data['password']),
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Da cap nhat mat khau. Ban co the dang nhap lai.',
        ]);
    }
}
