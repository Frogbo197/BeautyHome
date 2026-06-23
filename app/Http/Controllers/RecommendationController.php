<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class RecommendationController extends Controller
{
    public function index(Request $request)
    {
        $userId = $request->integer('user_id');

        $nutrition = DB::table('goiydinhduong')
            ->join('thucpham', 'goiydinhduong.ThucPhamID', '=', 'thucpham.ID')
            ->when($userId, fn ($query) => $query->where('goiydinhduong.NguoiDungID', $userId))
            ->select(
                'goiydinhduong.ID',
                'goiydinhduong.NguoiDungID',
                'goiydinhduong.LoaiBuaAn',
                'goiydinhduong.Ngay',
                'thucpham.Ten',
                'thucpham.Calo',
                'thucpham.Protein',
                'thucpham.Carb',
                'thucpham.ChatBeo'
            )
            ->orderByDesc('goiydinhduong.ID')
            ->limit(20)
            ->get();

        $exercise = DB::table('goiytapluyen')
            ->when($userId, fn ($query) => $query->where('NguoiDungID', $userId))
            ->orderByDesc('ID')
            ->limit(20)
            ->get();

        return response()->json([
            'success' => true,
            'nutrition' => $nutrition,
            'exercise' => $exercise,
        ]);
    }
}
