<?php

namespace App\Models;

use App\Enums\TriggerType;
use App\Enums\WorkflowRunStatus;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class WorkflowRun extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'workflow_id',
        'tenant_id',
        'triggered_by',
        'trigger_type',
        'status',
        'workflow_version',
        'workflow_definition',
        'input_data',
        'output_data',
        'error_message',
        'started_at',
        'completed_at',
        'timeout_at',
    ];

    protected $casts = [
        'status' => WorkflowRunStatus::class,
        'trigger_type' => TriggerType::class,
        'workflow_definition' => 'array',
        'input_data' => 'array',
        'output_data' => 'array',
        'started_at' => 'datetime',
        'completed_at' => 'datetime',
        'timeout_at' => 'datetime',
        'workflow_version' => 'integer',
    ];

    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    public function triggeredBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'triggered_by');
    }

    public function stepRuns(): HasMany
    {
        return $this->hasMany(StepRun::class);
    }

    public function getDurationSeconds(): ?int
    {
        if (!$this->started_at || !$this->completed_at) {
            return null;
        }
        return $this->completed_at->diffInSeconds($this->started_at);
    }

    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeRecent($query, int $hours = 24)
    {
        return $query->where('created_at', '>=', now()->subHours($hours));
    }
}
