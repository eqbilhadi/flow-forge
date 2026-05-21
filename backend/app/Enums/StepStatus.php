<?php

namespace App\Enums;

enum StepStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case SKIPPED = 'skipped';
    case RETRYING = 'retrying';
}
