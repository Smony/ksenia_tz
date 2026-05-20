<?php

namespace App\Models;

use App\Enums\OperatorStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Operator extends Model
{
    protected $fillable = [
        'name',
        'status',
        'active_calls',
        'max_concurrent_calls',
        'last_assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OperatorStatus::class,
            'last_assigned_at' => 'datetime',
        ];
    }

    public function calls(): HasMany
    {
        return $this->hasMany(Call::class);
    }

    public function hasCapacity(): bool
    {
        return $this->status === OperatorStatus::Available
            && $this->active_calls < $this->max_concurrent_calls;
    }
}
