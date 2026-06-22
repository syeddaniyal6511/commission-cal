<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommissionCalculation extends Model
{
    protected $fillable = [
        'contract_id',
        'formula_id',
        'formula_version',
        'commission',
        'variables_json',
        'steps_json',
    ];

    protected $casts = [
        'commission'     => 'float',
        'variables_json' => 'array',
        'steps_json'     => 'array',
    ];

    public function contract(): BelongsTo
    {
        return $this->belongsTo(Contract::class);
    }

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }
}
