<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UserGoalController extends Controller
{
    public function show(Request $request)
    {
        $userId = $request->integer('NguoiDungID') ?: $request->integer('user_id') ?: $request->integer('nguoi_dung_id');
        if (!$userId) {
            return response()->json(['success' => false, 'message' => 'Thiếu người dùng'], 422);
        }

        if (Schema::hasTable('user_goals')) {
            $data = DB::table('user_goals')->where('NguoiDungID', $userId)->get();
        } elseif (Schema::hasTable('muctieusuckhoe')) {
            $data = DB::table('muctieusuckhoe')->where('NguoiDungID', $userId)->get();
        } else {
            $data = collect();
        }

        return response()->json(['success' => true, 'data' => $data]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'NguoiDungID' => 'required_without:user_id|integer|exists:taikhoan,ID',
            'user_id' => 'required_without:NguoiDungID|integer|exists:taikhoan,ID',
            'Loai' => 'nullable|string|max:100',
            'LoaiMucTieu' => 'nullable|string|max:100',
            'TenMucTieu' => 'nullable|string|max:255',
            'GiaTri' => 'nullable|numeric',
            'GiaTriMucTieu' => 'nullable|numeric',
            'DonVi' => 'nullable|string|max:50',
            'DonViDo' => 'nullable|string|max:50',
            'ChuKyLap' => 'nullable|string|max:50',
            'BatNhac' => 'nullable',
            'GioNhac' => 'nullable|string|max:20',
            'NgayTrongTuan' => 'nullable|string|max:50',
        ]);

        $userId = $data['NguoiDungID'] ?? $data['user_id'];
        $type = $data['Loai'] ?? $data['LoaiMucTieu'] ?? 'TongQuat';
        $value = $data['GiaTri'] ?? $data['GiaTriMucTieu'] ?? null;

        if (Schema::hasTable('user_goals')) {
            $payload = [
                'GiaTri' => $value,
            ];
            foreach ([
                'DonVi' => $data['DonVi'] ?? $data['DonViDo'] ?? null,
                'ChuKyLap' => $data['ChuKyLap'] ?? null,
                'BatNhac' => array_key_exists('BatNhac', $data) ? (bool) $data['BatNhac'] : null,
                'GioNhac' => $data['GioNhac'] ?? null,
                'NgayTrongTuan' => $data['NgayTrongTuan'] ?? null,
                'NgayCapNhat' => now('Asia/Ho_Chi_Minh'),
            ] as $column => $columnValue) {
                if ($columnValue !== null && Schema::hasColumn('user_goals', $column)) {
                    $payload[$column] = $columnValue;
                }
            }
            $goalExists = DB::table('user_goals')
                ->where('NguoiDungID', $userId)
                ->where('Loai', $type)
                ->exists();
            if (!$goalExists && Schema::hasColumn('user_goals', 'NgayTao')) {
                $payload['NgayTao'] = now('Asia/Ho_Chi_Minh');
            }

            DB::table('user_goals')->updateOrInsert(
                ['NguoiDungID' => $userId, 'Loai' => $type],
                $payload
            );

            $saved = DB::table('user_goals')
                ->where('NguoiDungID', $userId)
                ->where('Loai', $type)
                ->first();
        } elseif (Schema::hasTable('muctieusuckhoe')) {
            DB::table('muctieusuckhoe')->updateOrInsert(
                ['NguoiDungID' => $userId, 'LoaiMucTieu' => $type],
                [
                    'TenMucTieu' => $data['TenMucTieu'] ?? $type,
                    'GiaTriMucTieu' => $value,
                    'NgayBatDau' => now('Asia/Ho_Chi_Minh')->toDateString(),
                    'TrangThai' => 'DangThucHien',
                    'DonViDo' => $data['DonViDo'] ?? $data['DonVi'] ?? null,
                ]
            );

            $saved = DB::table('muctieusuckhoe')
                ->where('NguoiDungID', $userId)
                ->where('LoaiMucTieu', $type)
                ->first();
        } else {
            $saved = null;
        }

        return response()->json([
            'success' => true,
            'data' => $saved,
            'muc_tieu_ml' => (float) ($saved->GiaTri ?? $saved->GiaTriMucTieu ?? $value ?? 0),
        ]);
    }
}
