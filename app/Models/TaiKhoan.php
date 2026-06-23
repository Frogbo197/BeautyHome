<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;

class TaiKhoan extends Authenticatable
{
    protected $table = 'taikhoan';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $hidden = ['MatKhauHash'];

    public function getAuthPasswordName()
    {
        return 'MatKhauHash';
    }

    public function getAuthPassword()
    {
        return $this->MatKhauHash;
    }

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
