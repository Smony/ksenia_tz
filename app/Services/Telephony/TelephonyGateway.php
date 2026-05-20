<?php

namespace App\Services\Telephony;

use App\Models\Call;
use App\Models\Operator;

interface TelephonyGateway
{
    public function dispatchCallAssigned(Call $call, Operator $operator): void;
}
