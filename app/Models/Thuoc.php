<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Thuoc extends Model
{
    use HasFactory;

    protected $table = 'thuoc';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'TenThuoc',
        'MoTa',
        'TacDungPhu',
        'LieuLuong',
        'DonVi',
        'SoLanMoiNgay',
        'GhiChu',
        'CanhBao',
    ];

    protected $casts = [
        'LieuLuong' => 'float',
        'SoLanMoiNgay' => 'integer',
    ];
}