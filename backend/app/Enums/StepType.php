<?php

namespace App\Enums;

enum StepType: string
{
    case HTTP = 'http';
    case SCRIPT = 'script';
    case DELAY = 'delay';
    case CONDITION = 'condition';
}
