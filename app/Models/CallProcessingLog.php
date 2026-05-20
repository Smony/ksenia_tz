<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CallProcessingLog extends Model
{
    protected $fillable = ['call_id', 'step', 'level', 'context', 'message'];

    protected function casts(): array
    {
        return [
            'context' => 'array',
        ];
    }

    public function call(): BelongsTo
    {
        return $this->belongsTo(Call::class);
    }
}
