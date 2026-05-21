<?php

namespace App\Enums;

enum TriggerType: string
{
    case MANUAL = 'manual';
    case SCHEDULE = 'schedule';
    case WEBHOOK = 'webhook';
}
