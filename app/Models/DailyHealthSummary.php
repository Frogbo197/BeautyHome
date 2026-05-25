<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyHealthSummary extends Model
{
    protected $table = 'tomtatsuckhoehangngay';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'NguoiDungID',
        'Ngay',
        'TongCaloVao',
        'TongCaloRa',
        'MucTieu',
        'TongLuongNuoc',
        'TongBuocDi',
        'ThoiGianHoatDong',
        'TongProtein',
        'TongCarb',
        'TongChatBeo',
        'DiemSucKhoe',
        'TrangThaiHoanThanh',
        'AIPhanTich',
        'NgayTao',
    ];
}
