<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class DependentVariable extends Model
{
    protected $fillable = ['formula_id', 'name', 'expression', 'execution_order'];

    public function formula(): BelongsTo
    {
        return $this->belongsTo(Formula::class);
    }
}
