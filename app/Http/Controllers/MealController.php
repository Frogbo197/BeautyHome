<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BuaAn;

class MealController extends Controller
{
    public function index()
    {
        return response()->json(
            BuaAn::all()
        );
    }

    public function store(Request $request)
    {
        $meal = BuaAn::create([
            'NguoiDungID' => $request->NguoiDungID,
            'Ngay' => $request->Ngay,
            'LoaiBuaAn' => $request->LoaiBuaAn
        ]);

        return response()->json([
            'success' => true,
            'data' => $meal
        ]);
    }
}