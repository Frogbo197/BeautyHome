<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoatDong extends Model
{
    protected $table = 'hoatdong';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'ID',
        'TenHoatDong',
        'Calo',
        'MoTa',
        'MET',
        'Category',
        'Intensity',
        'Calories30Min',
        'Calories60Min'

    ];
}
