<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class ThongBaoController extends Controller
{
    public function index(Request $request)
    {
        $userId = $this->userId($request);
        if (!$userId || !Schema::hasTable('thongbao')) {
            return response()->json(['success' => true, 'du_lieu' => []]);
        }

        $items = DB::table('thongbao')
            ->where('NguoiDungID', $userId)
            ->orderByDesc('ThoiGian')
            ->limit(100)
            ->get();

        return response()->json(['success' => true, 'du_lieu' => $items]);
    }

    public function demChuaDoc(Request $request)
    {
        $userId = $this->userId($request);
        if (!$userId || !Schema::hasTable('thongbao')) {
            return response()->json(['success' => true, 'so_chua_doc' => 0]);
        }

        $count = DB::table('thongbao')
            ->where('NguoiDungID', $userId)
            ->where('DaDoc', 0)
            ->count();

        return response()->json(['success' => true, 'so_chua_doc' => $count]);
    }

    public function ghiNhan(Request $request)
    {
        if (!Schema::hasTable('thongbao')) {
            return response()->json(['success' => true], 201);
        }

        $data = $request->validate([
            'nguoi_dung_id' => 'required_without:NguoiDungID|integer|exists:taikhoan,ID',
            'NguoiDungID' => 'required_without:nguoi_dung_id|integer|exists:taikhoan,ID',
            'loai_thong_bao' => 'nullable|string|max:100',
            'LoaiThongBao' => 'nullable|string|max:100',
            'noi_dung' => 'nullable|string',
            'NoiDung' => 'nullable|string',
        ]);

        DB::table('thongbao')->insert([
            'NguoiDungID' => $data['nguoi_dung_id'] ?? $data['NguoiDungID'],
            'LoaiThongBao' => $data['loai_thong_bao'] ?? $data['LoaiThongBao'] ?? 'HeThong',
            'ThoiGian' => now('Asia/Ho_Chi_Minh'),
            'NoiDung' => $data['noi_dung'] ?? $data['NoiDung'] ?? '',
            'TrangThaiGui' => 'DaGui',
            'DaDoc' => 0,
        ]);

        return response()->json(['success' => true], 201);
    }

    public function danhDauDaDoc(int $id)
    {
        if (Schema::hasTable('thongbao')) {
            DB::table('thongbao')->where('ID', $id)->update(['DaDoc' => 1]);
        }

        return response()->json(['success' => true]);
    }

    public function docTatCa(Request $request)
    {
        $userId = $this->userId($request);
        if ($userId && Schema::hasTable('thongbao')) {
            DB::table('thongbao')->where('NguoiDungID', $userId)->update(['DaDoc' => 1]);
        }

        return response()->json(['success' => true]);
    }

    private function userId(Request $request): int
    {
        return $request->integer('nguoi_dung_id')
            ?: $request->integer('NguoiDungID')
            ?: $request->integer('user_id');
    }
}
