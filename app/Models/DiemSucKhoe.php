<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DiemSucKhoe extends Model
{
    protected $table = 'diemsuckhoe';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'NguoiDungID',
        'Diem',
        'NgayTinh',
        'NhanXetAI'
    ];
}

