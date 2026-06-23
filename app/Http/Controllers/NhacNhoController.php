<?php

namespace App\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NhacNhoController extends Controller
{
    private const GROUPS = ['VanDong', 'UongNuoc', 'Thuoc', 'AnSang', 'AnTrua', 'AnToi'];

    public function index(Request $request)
    {
        $userId = $request->integer('nguoi_dung_id')
            ?: $request->integer('NguoiDungID')
            ?: $request->integer('user_id');

        $empty = collect(self::GROUPS)->mapWithKeys(fn ($group) => [$group => []])->all();
        if (!$userId || !Schema::hasTable('nhacnho')) {
            return response()->json(['success' => true, 'du_lieu' => $empty]);
        }

        $rows = DB::table('nhacnho')
            ->where('NguoiDungID', $userId)
            ->orderBy('ThoiGian')
            ->get();

        $data = $empty;
        foreach ($rows as $row) {
            $group = $row->LoaiDoiTuong ?: 'Khac';
            $data[$group] ??= [];
            $data[$group][] = $this->formatReminder($row);
        }

        return response()->json(['success' => true, 'du_lieu' => $data]);
    }

    public function luuNhom(Request $request)
    {
        $data = $request->validate([
            'nguoi_dung_id' => 'required_without:NguoiDungID|integer|exists:taikhoan,ID',
            'NguoiDungID' => 'required_without:nguoi_dung_id|integer|exists:taikhoan,ID',
            'loai' => 'required|string|max:100',
            'danh_sach' => 'nullable|array',
            'danh_sach.*.gio' => 'nullable|string|max:5',
            'danh_sach.*.trang_thai' => 'nullable|string|max:20',
            'danh_sach.*.ngay_trong_tuan' => 'nullable',
        ]);

        if (!Schema::hasTable('nhacnho')) {
            return response()->json(['success' => true, 'du_lieu' => []]);
        }

        $userId = $data['nguoi_dung_id'] ?? $data['NguoiDungID'];
        $type = $data['loai'];

        DB::transaction(function () use ($userId, $type, $data) {
            DB::table('nhacnho')
                ->where('NguoiDungID', $userId)
                ->where('LoaiDoiTuong', $type)
                ->delete();

            foreach (($data['danh_sach'] ?? []) as $item) {
                $time = $this->timeFromInput($item['gio'] ?? '08:00');
                $insert = [
                    'NguoiDungID' => $userId,
                    'LoaiDoiTuong' => $type,
                    'DoiTuongId' => null,
                    'ThoiGian' => now('Asia/Ho_Chi_Minh')->setTimeFromTimeString($time),
                    'LapLai' => 1,
                    'TrangThai' => $item['trang_thai'] ?? 'Bat',
                ];

                if (Schema::hasColumn('nhacnho', 'NgayTrongTuan')) {
                    $insert['NgayTrongTuan'] = $item['ngay_trong_tuan'] ?? '1,2,3,4,5,6,7';
                }

                DB::table('nhacnho')->insert($insert);
            }
        });

        return response()->json(['success' => true]);
    }

    public function doiTrangThai(Request $request, int $id)
    {
        $data = $request->validate(['trang_thai' => 'required|string|max:20']);
        if (Schema::hasTable('nhacnho')) {
            DB::table('nhacnho')->where('ID', $id)->update(['TrangThai' => $data['trang_thai']]);
        }

        return response()->json(['success' => true]);
    }

    public function xoa(int $id)
    {
        if (Schema::hasTable('nhacnho')) {
            DB::table('nhacnho')->where('ID', $id)->delete();
        }

        return response()->json(['success' => true]);
    }

    private function formatReminder($row): array
    {
        return [
            'id' => (int) $row->ID,
            'gio' => $row->ThoiGian ? Carbon::parse($row->ThoiGian)->format('H:i') : '08:00',
            'lap_lai' => (bool) $row->LapLai,
            'trang_thai' => $row->TrangThai ?? 'Bat',
            'ngay_trong_tuan' => $row->NgayTrongTuan ?? '1,2,3,4,5,6,7',
        ];
    }

    private function timeFromInput(string $value): string
    {
        if (preg_match('/^\d{2}:\d{2}$/', $value)) {
            return "{$value}:00";
        }
        if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $value)) {
            return $value;
        }

        return '08:00:00';
    }
}
