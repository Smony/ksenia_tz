<?php

namespace App\Enums;

enum CallStatus: string
{
    case Incoming = 'incoming';
    case Processing = 'processing';
    case Assigned = 'assigned';
    case Failed = 'failed';
}
