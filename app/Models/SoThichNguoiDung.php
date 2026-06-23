<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SoThichNguoiDung extends Model
{
    protected $table = 'sothichnguoidung';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'NguoiDung',
        'MucTieu',
        'MucVanDong',
        'CheDoAn'
    ];
}

