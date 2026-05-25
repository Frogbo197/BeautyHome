<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ActivityLog extends Model
{
    protected $table = 'activity_logs';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'NguoiDungID',
        'TenHoatDong',
        'LoaiHoatDong',
        'ThoiLuongPhut',
        'CaloriesDot',
        'DistanceKm',
        'Steps',
        'MucDo',
        'NgayHoatDong',
        'GioBatDau',
        'Nguon',
        'IsCompleted',
        'RecommendationID',
        'CreatedAt',
    ];
}
