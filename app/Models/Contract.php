<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Contract extends Model
{
    use HasFactory;

    protected $fillable = [
        'contract_no',
        'annual_usage',
        'contract_value',
        'contract_length',
        'risk_score',
    ];

    protected $casts = [
        'annual_usage'    => 'float',
        'contract_value'  => 'float',
        'contract_length' => 'integer',
        'risk_score'      => 'float',
    ];
}
