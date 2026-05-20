<?php

namespace App\Services\Telephony;

use App\Exceptions\TelephonyDispatchException;
use App\Models\Call;
use App\Models\Operator;
use Illuminate\Support\Facades\Http;

class HttpTelephonyGateway implements TelephonyGateway
{
    public function __construct(
        private readonly string $endpoint,
        private readonly int $timeoutSeconds = 5,
    ) {
    }

    public function dispatchCallAssigned(Call $call, Operator $operator): void
    {
        $response = Http::timeout($this->timeoutSeconds)
            ->retry(2, 200)
            ->post($this->endpoint, [
                'call_id' => $call->id,
                'external_id' => $call->external_id,
                'operator_id' => $operator->id,
                'phone' => $call->phone,
            ]);

        if (! $response->successful()) {
            throw new TelephonyDispatchException(
                "Telephony responded with HTTP {$response->status()}"
            );
        }
    }
}
