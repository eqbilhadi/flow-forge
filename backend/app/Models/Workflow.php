<?php

namespace App\Models;

use App\Enums\TriggerType;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Workflow extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    protected $fillable = [
        'tenant_id',
        'created_by',
        'name',
        'description',
        'definition',
        'version',
        'is_active',
        'trigger_type',
        'cron_expression',
        'timeout_seconds',
        'tags',
    ];

    protected $casts = [
        'definition' => 'array',
        'is_active' => 'boolean',
        'trigger_type' => TriggerType::class,
        'tags' => 'array',
        'version' => 'integer',
        'timeout_seconds' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function runs(): HasMany
    {
        return $this->hasMany(WorkflowRun::class);
    }

    public function versions(): HasMany
    {
        return $this->hasMany(WorkflowVersion::class);
    }

    public function webhookTokens(): HasMany
    {
        return $this->hasMany(WebhookToken::class);
    }

    /**
     * Scope to tenant — always apply for multi-tenancy
     */
    public function scopeForTenant($query, string $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}
