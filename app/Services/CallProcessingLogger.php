<?php

namespace App\Services;

use App\Models\Call;
use App\Models\CallProcessingLog;
use Illuminate\Support\Facades\Log;

class CallProcessingLogger
{
    public function info(Call $call, string $step, string $message, array $context = []): void
    {
        $this->write($call, $step, 'info', $message, $context);
    }

    public function warning(Call $call, string $step, string $message, array $context = []): void
    {
        $this->write($call, $step, 'warning', $message, $context);
    }

    public function error(Call $call, string $step, string $message, array $context = []): void
    {
        $this->write($call, $step, 'error', $message, $context);
    }

    private function write(Call $call, string $step, string $level, string $message, array $context): void
    {
        CallProcessingLog::create([
            'call_id' => $call->id,
            'step' => $step,
            'level' => $level,
            'message' => $message,
            'context' => $context,
        ]);

        Log::channel('calls')->{$level}($message, [
            'call_id' => $call->id,
            'step' => $step,
            ...$context,
        ]);
    }
}
