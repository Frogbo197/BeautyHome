<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HeThongCanhBao extends Model
{
    protected $table = 'he_thong_canh_baos';

    protected $fillable = [
        'user_id',
        'alert_key',
        'loai_canh_bao',
        'noi_dung_chi_tiet',
        'muc_do_nguy_hiem',
        'status',
        'metadata',
        'detected_at',
    ];

    protected $casts = [
        'metadata' => 'array',
        'detected_at' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(TaiKhoan::class, 'user_id', 'ID');
    }
}
