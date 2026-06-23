<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class HomeController extends Controller
{
    public function index($id)
    {

        $user = DB::table('hosonguoidung')
            ->where('NguoiDungID', $id)
            ->first();

        $healthScore = DB::table('diemsuckhoe')
            ->where('NguoiDungID', $id)
            ->latest('ID')
            ->first();

        $health = null;
        if (Schema::hasTable('chitiethoatdong') && Schema::hasTable('lichhoatdong')) {
            $health = DB::table('chitiethoatdong')
                ->join(
                    'lichhoatdong',
                    'chitiethoatdong.LichHoatDongID',
                    '=',
                    'lichhoatdong.ID'
                )
                ->where('lichhoatdong.NguoiDungID', $id)
                ->select('chitiethoatdong.*')
                ->latest('chitiethoatdong.ID')
                ->first();
        }

        $water = DB::table('theodoinuoc')
            ->where('NguoiDungID', $id)
            ->latest('ID')
            ->first();

        $foods = collect();
        if (Schema::hasTable('goiydinhduong') && Schema::hasTable('thucpham')) {
            $foods = DB::table('goiydinhduong')
                ->leftJoin(
                    'thucpham',
                    'goiydinhduong.ThucPhamID',
                    '=',
                    'thucpham.ID'
                )
                ->where('goiydinhduong.NguoiDungID', $id)
                ->where(function ($query) {
                    $query
                        ->whereNotNull('thucpham.Ten')
                        ->orWhereNotNull('goiydinhduong.TenMonAn');
                })
                ->selectRaw('COALESCE(thucpham.Ten, goiydinhduong.TenMonAn) as Ten')
                ->limit(5)
                ->get();
        }

        $medicines = Schema::hasTable('lichdungthuoc')
            ? DB::table('lichdungthuoc')->where('NguoiDungID', $id)->count()
            : 0;

        return response()->json([
            'user' => $user,
            'healthScore' => $healthScore,
            'health' => $health,
            'water' => $water,
            'foods' => $foods,
            'medicines' => $medicines,
        ]);
    }
}

