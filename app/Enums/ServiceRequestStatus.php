<?php

namespace App\Enums;

enum ServiceRequestStatus: string
{
    case Open = 'open';
    case Assigned = 'assigned';
    case Cancelled = 'cancelled';
}
