<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Water extends Model
{
    protected $table = 'theodoinuoc';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'NguoiDungID',
        'Ngay',
        'LuongNuoc'
    ];
}

