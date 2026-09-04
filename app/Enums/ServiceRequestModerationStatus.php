<?php

namespace App\Enums;

enum ServiceRequestModerationStatus: string
{
    case Visible = 'visible';
    case Hidden = 'hidden';
}
