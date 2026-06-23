<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ChatHistory extends Model
{
    protected $table = 'lichsutrochuyen';

    protected $primaryKey = 'ID';

    public $timestamps = true;

    protected $fillable = [

        'NguoiDungID',

        'SessionID',

        'UserMessage',

        'BotReply',

        'Model',

        'ThoiGian'
    ];

    protected $casts = [

        'ThoiGian' => 'datetime',
    ];

    public function user()
    {
        return $this->belongsTo(
            TaiKhoan::class,
            'NguoiDungID'
        );
    }
}
