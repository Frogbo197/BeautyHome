<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoiYDinhDuong extends Model
{
    protected $table = 'goiydinhduong';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [

        'NguoiDungID',

        'ThucPhamID',

        'LoaiBuaAn',

        'Ngay',

        'PhanTichID'
    ];

    protected $casts = [

        'Ngay' => 'date'
    ];

    /*
    |--------------------------------------------------------------------------
    | RELATIONSHIPS
    |--------------------------------------------------------------------------
    */

    public function user()
    {
        return $this->belongsTo(
            TaiKhoan::class,
            'NguoiDungID'
        );
    }

    public function phanTich()
    {
        return $this->belongsTo(
            PhanTichSucKhoeAI::class,
            'PhanTichID'
        );
    }
}
