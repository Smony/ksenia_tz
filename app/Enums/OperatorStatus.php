<?php

namespace App\Enums;

enum OperatorStatus: string
{
    case Available = 'available';
    case Busy = 'busy';
    case Offline = 'offline';
}
