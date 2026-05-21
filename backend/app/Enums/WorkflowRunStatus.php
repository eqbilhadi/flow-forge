<?php

namespace App\Enums;

enum WorkflowRunStatus: string
{
    case PENDING = 'pending';
    case RUNNING = 'running';
    case SUCCESS = 'success';
    case FAILED = 'failed';
    case TIMEOUT = 'timeout';
    case CANCELLED = 'cancelled';
}
