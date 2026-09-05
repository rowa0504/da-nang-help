<?php

namespace App\Enums;

enum ServiceJobStatus: string
{
    case Assigned = 'assigned';
    case InProgress = 'in_progress';
    case AwaitingConfirmation = 'awaiting_confirmation';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
}
