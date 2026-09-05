<?php

namespace App\Enums;

enum JobCompletionMode: string
{
    case Manual = 'manual';
    case Auto = 'auto';
}
