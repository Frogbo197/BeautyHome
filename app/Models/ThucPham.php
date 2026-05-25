<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ThucPham extends Model
{
    /*
    |--------------------------------------------------------------------------
    | TABLE
    |--------------------------------------------------------------------------
    */

    protected $table = 'thucpham';

    protected $primaryKey = 'ID';

    public $timestamps = false;

    /*
    |--------------------------------------------------------------------------
    | FILLABLE
    |--------------------------------------------------------------------------
    */

    protected $fillable = [

        'Ten',

        'Calo',

        'Protein',

        'Carb',

        'ChatBeo',

        'DonVi',

        'KhoiLuongGram',

        'LoaiThucPham',

        'Keywords',

        'IsHealthy',

        'CreatedAt'
    ];

    /*
    |--------------------------------------------------------------------------
    | CASTS
    |--------------------------------------------------------------------------
    */

    protected $casts = [

        'Calo' => 'float',

        'Protein' => 'float',

        'Carb' => 'float',

        'ChatBeo' => 'float',

        'KhoiLuongGram' => 'float',

        'IsHealthy' => 'boolean',

        'CreatedAt' => 'datetime'
    ];

    /*
    |--------------------------------------------------------------------------
    | DEFAULT ATTRIBUTES
    |--------------------------------------------------------------------------
    */

    protected $attributes = [

        'IsHealthy' => true
    ];

    /*
    |--------------------------------------------------------------------------
    | HELPERS
    |--------------------------------------------------------------------------
    */

    public function isHealthy()
    {
        return $this->IsHealthy === true;
    }

    public function getNutritionSummaryAttribute()
    {
        return
            "{$this->Calo} calo | "
            ."P: {$this->Protein}g | "
            ."C: {$this->Carb}g | "
            ."F: {$this->ChatBeo}g";
    }

    /*
    |--------------------------------------------------------------------------
    | SCOPES
    |--------------------------------------------------------------------------
    */

    public function scopeHealthy($query)
    {
        return $query->where(
            'IsHealthy',
            true
        );
    }

    public function scopeByCategory(
        $query,
        $category
    ) {

        return $query->where(
            'LoaiThucPham',
            $category
        );
    }
}