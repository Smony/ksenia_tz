<?php

namespace App\Services;

use App\Enums\OperatorStatus;
use App\Exceptions\NoAvailableOperatorException;
use App\Models\Operator;
use Illuminate\Support\Facades\DB;

class OperatorSelector
{
    /**
     * Выбор оператора под блокировкой — иначе два воркера заберут одного и того же.
     */
    public function reserveNext(): Operator
    {
        return DB::transaction(function () {
            $operator = Operator::query()
                ->where('status', OperatorStatus::Available)
                ->whereColumn('active_calls', '<', 'max_concurrent_calls')
                ->orderBy('active_calls')
                ->orderBy('last_assigned_at')
                ->lockForUpdate()
                ->first();

            if ($operator === null) {
                throw new NoAvailableOperatorException('No available operators');
            }

            $operator->increment('active_calls');
            $operator = $operator->fresh();

            $operator->update([
                'last_assigned_at' => now(),
                'status' => $operator->active_calls >= $operator->max_concurrent_calls
                    ? OperatorStatus::Busy
                    : OperatorStatus::Available,
            ]);

            return $operator->fresh();
        });
    }

    public function release(Operator $operator): void
    {
        DB::transaction(function () use ($operator) {
            $locked = Operator::query()->whereKey($operator->id)->lockForUpdate()->firstOrFail();

            $locked->decrement('active_calls');

            if ($locked->active_calls <= 0) {
                $locked->update([
                    'active_calls' => 0,
                    'status' => OperatorStatus::Available,
                ]);
            }
        });
    }
}
