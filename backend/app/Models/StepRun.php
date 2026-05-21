<?php

namespace App\Models;

use App\Enums\StepStatus;
use App\Enums\StepType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class StepRun extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workflow_run_id',
        'tenant_id',
        'step_id',
        'step_name',
        'step_type',
        'status',
        'input_data',
        'output_data',
        'error_message',
        'retry_count',
        'started_at',
        'completed_at',
        'logs',
    ];

    protected $casts = [
        'status' => StepStatus::class,
        'step_type' => StepType::class,
        'input_data' => 'array',
        'output_data' => 'array',
        'logs' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'retry_count' => 'integer',
    ];

    public function workflowRun(): BelongsTo
    {
        return $this->belongsTo(WorkflowRun::class);
    }

    public function getDurationMs(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        return $this->completed_at->diffInMilliseconds($this->started_at);
    }
}
