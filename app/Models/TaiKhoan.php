<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class TaiKhoan extends Model
{
    protected $table = 'taikhoan';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    public function profile()
{
    return $this->hasOne(
        HoSoNguoiDung::class,
        'NguoiDungID'
    );
}

    protected $fillable = [
        'Email',
        'MatKhauHash',
        'TrangThaiHoatDong',
        'LanDangNhapCuoi',
        'NgayTao',
    ];
}
