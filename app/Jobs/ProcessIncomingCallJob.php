<?php

namespace App\Jobs;

use App\Services\IncomingCallProcessor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ProcessIncomingCallJob implements ShouldQueue, ShouldBeUnique
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;

    public array $backoff = [5, 15, 60, 120];

    public int $uniqueFor = 300;

    public function __construct(
        public readonly int $callId,
    ) {
        $this->onQueue('incoming-calls');
    }

    public function uniqueId(): string
    {
        return (string) $this->callId;
    }

    public function handle(IncomingCallProcessor $processor): void
    {
        $processor->process($this->callId);
    }

    public function tags(): array
    {
        return ['call:'.$this->callId];
    }
}
