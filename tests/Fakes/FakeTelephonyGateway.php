<?php

namespace Tests\Fakes;

use App\Models\Call;
use App\Models\Operator;
use App\Services\Telephony\TelephonyGateway;

class FakeTelephonyGateway implements TelephonyGateway
{
    public int $dispatched = 0;

    public bool $shouldFail = false;

    public function dispatchCallAssigned(Call $call, Operator $operator): void
    {
        if ($this->shouldFail) {
            throw new \App\Exceptions\TelephonyDispatchException('Telephony down');
        }

        $this->dispatched++;
    }
}
