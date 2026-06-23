<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DailyHealthSummary extends Model
{
    protected $table = 'daily_health_summary';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    protected $fillable = [
        'NguoiDungID',
        'Ngay',
        'TotalCaloriesIn',
        'TotalCaloriesOut',
        'NetCalories',
        'TotalWaterML',
        'TotalSteps',
        'ActivityMinutes',
        'TotalFat',
        'DiemSucKhoe',
        'GoalStatus',
        'AIComment',
        'CreatedAt',
    ];
}
