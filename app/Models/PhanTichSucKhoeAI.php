<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PhanTichSucKhoeAI extends Model
{
    protected $table = 'phantichsuckhoeai';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'NguoiDungID',
        'LoaiPhanTich',
        'LoaiNguon',
        'KetQua',
        'DoTinCay',
        'MoHinh',
        'ThoiGianXuLy',
        'NgayPhanTich',
        'DuLieuDauVao',
        'Prompt',
        'prompt',
        'Model',
    ];
}
