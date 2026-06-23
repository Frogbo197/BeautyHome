<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChiSoSucKhoe extends Model
{
    protected $table = 'chisosuckhoe';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'NguoiDungID',
        'Ngay',
        'CanNang',
        'HuyetAp',
        'NhipTim',
        'BMI'
    ];
}

