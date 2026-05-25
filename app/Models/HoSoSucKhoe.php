<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoSoSucKhoe extends Model
{
    protected $table = 'hososuckhoe';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'NguoiDungID',
        'NhomMau',
        'BenhNen',
        'TheTrang'
    ];
}

