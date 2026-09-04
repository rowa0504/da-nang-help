<?php

namespace App\Enums;

enum ServiceRequestUrgency: string
{
    case Normal = 'normal';
    case Urgent = 'urgent';
}
