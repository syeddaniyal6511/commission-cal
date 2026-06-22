<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Formula extends Model
{
    protected $fillable = ['version', 'expression', 'is_active'];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function dependentVariables(): HasMany
    {
        return $this->hasMany(DependentVariable::class)->orderBy('execution_order');
    }
}
