<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChiTietBuaAn extends Model
{
    protected $table = 'chitietbuaan';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'BuaAnID',
        'ThucPhamID',
        'SoLuong',
        'TongCalo',
        'TongProtein',
        'TongCarb',
        'TongFat',
        'CaloriesMoi100g'
    ];

    public function buaAn()
    {
        return $this->belongsTo(
            BuaAn::class,
            'BuaAnID'
        );
    }

    public function thucPham()
    {
        return $this->belongsTo(
            ThucPham::class,
            'ThucPhamID'
        );
    }
}
