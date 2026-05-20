<?php

namespace App\Models;

use App\Enums\CallStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Call extends Model
{
    protected $fillable = [
        'phone',
        'client_id',
        'operator_id',
        'status',
        'external_id',
        'assigned_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => CallStatus::class,
            'assigned_at' => 'datetime',
        ];
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function operator(): BelongsTo
    {
        return $this->belongsTo(Operator::class);
    }

    public function logs(): HasMany
    {
        return $this->hasMany(CallProcessingLog::class);
    }

    public function isAlreadyHandled(): bool
    {
        return in_array($this->status, [CallStatus::Assigned, CallStatus::Processing], true)
            && $this->operator_id !== null;
    }
}
