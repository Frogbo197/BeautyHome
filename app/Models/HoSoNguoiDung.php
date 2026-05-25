<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HoSoNguoiDung extends Model
{
    protected $table = 'hosonguoidung';

    protected $primaryKey = 'ID';

    public $timestamps = false;
    public function user()
{
    return $this->belongsTo(
        TaiKhoan::class,
        'NguoiDungID'
    );
}

    protected $fillable = [
        'NguoiDungID',
        'Ten',
        'NgaySinh',
        'GioiTinh',
        'ChieuCao',
        'CanNang',
        'AnhDaiDien'
    ];
}

