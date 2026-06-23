<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GoiYLuyenTap extends Model
{
    protected $table = 'goiytapluyen';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [

        'NguoiDungID',

        'TenBaiTap',

        'ThoiLuong',

        'CaloDot',

        'NgayTao',

        'RecommendationScore',

        'IsCompleted',

        'FeedbackScore',

        'ModelVersion',

        'DifficultyLevel',

        'GoalType',

        'GeneratedReason',

        'SourceType',

        'CompletedAt'
    ];

    protected $casts = [

        'NgayTao' => 'datetime',

        'CompletedAt' => 'datetime',

        'IsCompleted' => 'boolean',

        'RecommendationScore' => 'float',

        'FeedbackScore' => 'float',

        'CaloDot' => 'float',

        'ThoiLuong' => 'integer'
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

    /*
    |--------------------------------------------------------------------------
    | HELPER METHODS
    |--------------------------------------------------------------------------
    */

    public function markAsCompleted($feedbackScore = null)
    {
        $this->update([
            'IsCompleted' => true,
            'CompletedAt' => now(),
            'FeedbackScore' => $feedbackScore
        ]);
    }

    public function isHighRecommendation()
    {
        return $this->RecommendationScore >= 90;
    }
}