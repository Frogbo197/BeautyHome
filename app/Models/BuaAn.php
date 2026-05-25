<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BuaAn extends Model
{
    protected $table = 'buaan';
    protected $primaryKey = 'ID';
    public $timestamps = false;

    protected $fillable = [
        'NguoiDungID',
        'TenMonAn',
        'LoaiBuaAn',
        'Ngay',
    ];

    public function chiTiet()
    {
        return $this->hasMany(ChiTietBuaAn::class, 'BuaAnID');
    }
}
