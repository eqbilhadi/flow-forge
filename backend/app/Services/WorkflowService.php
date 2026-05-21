<?php

namespace App\Services;

use App\Enums\TriggerType;
use App\Enums\WorkflowRunStatus;
use App\Jobs\ExecuteWorkflowJob;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowRun;
use App\Models\WorkflowVersion;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WorkflowService
{
    public function __construct(
        private readonly DAGParser $dagParser,
    ) {}

    /**
     * Create a new workflow with version tracking.
     */
    public function create(User $user, array $data): Workflow
    {
        $this->validateDefinition($data['definition']);

        return DB::transaction(function () use ($user, $data) {
            $workflow = Workflow::create([
                'tenant_id' => $user->tenant_id,
                'created_by' => $user->id,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'definition' => $data['definition'],
                'version' => 1,
                'is_active' => $data['is_active'] ?? true,
                'trigger_type' => $data['trigger_type'] ?? TriggerType::MANUAL->value,
                'cron_expression' => $data['cron_expression'] ?? null,
                'timeout_seconds' => $data['timeout_seconds'] ?? 3600,
                'tags' => $data['tags'] ?? [],
            ]);

            // Save initial version
            WorkflowVersion::create([
                'workflow_id' => $workflow->id,
                'tenant_id' => $user->tenant_id,
                'version' => 1,
                'definition' => $data['definition'],
                'created_by' => $user->id,
                'change_notes' => 'Initial version',
            ]);

            return $workflow;
        });
    }

    /**
     * Update workflow and create a new version snapshot.
     */
    public function update(Workflow $workflow, User $user, array $data): Workflow
    {
        if (isset($data['definition'])) {
            $this->validateDefinition($data['definition']);
        }

        return DB::transaction(function () use ($workflow, $user, $data) {
            $newVersion = $workflow->version + 1;

            $workflow->update(array_merge($data, [
                'version' => $newVersion,
            ]));

            if (isset($data['definition'])) {
                WorkflowVersion::create([
                    'workflow_id' => $workflow->id,
                    'tenant_id' => $workflow->tenant_id,
                    'version' => $newVersion,
                    'definition' => $data['definition'],
                    'created_by' => $user->id,
                    'change_notes' => $data['change_notes'] ?? "Updated to v{$newVersion}",
                ]);
            }

            return $workflow->fresh();
        });
    }

    /**
     * Roll back to a specific version.
     */
    public function rollback(Workflow $workflow, User $user, int $version): Workflow
    {
        $versionRecord = WorkflowVersion::where('workflow_id', $workflow->id)
            ->where('version', $version)
            ->firstOrFail();

        return $this->update($workflow, $user, [
            'definition' => $versionRecord->definition,
            'change_notes' => "Rolled back to v{$version}",
        ]);
    }

    /**
     * Trigger a workflow run manually or via webhook.
     */
    public function trigger(
        Workflow $workflow,
        TriggerType $triggerType,
        array $inputData = [],
        ?string $triggeredBy = null
    ): WorkflowRun {
        if (!$workflow->is_active) {
            throw new InvalidArgumentException('Workflow is not active.');
        }

        $run = WorkflowRun::create([
            'workflow_id' => $workflow->id,
            'tenant_id' => $workflow->tenant_id,
            'triggered_by' => $triggeredBy,
            'trigger_type' => $triggerType,
            'status' => WorkflowRunStatus::PENDING,
            'workflow_version' => $workflow->version,
            'workflow_definition' => $workflow->definition,
            'input_data' => $inputData,
            'timeout_at' => now()->addSeconds($workflow->timeout_seconds ?? 3600),
        ]);

        // Dispatch to queue
        ExecuteWorkflowJob::dispatch($run)->onQueue('workflows');

        return $run;
    }

    /**
     * Cancel a running workflow.
     */
    public function cancel(WorkflowRun $run): WorkflowRun
    {
        if (!in_array($run->status, [WorkflowRunStatus::PENDING, WorkflowRunStatus::RUNNING])) {
            throw new InvalidArgumentException('Only pending or running workflows can be cancelled.');
        }

        $run->update([
            'status' => WorkflowRunStatus::CANCELLED,
            'completed_at' => now(),
        ]);

        return $run->fresh();
    }

    private function validateDefinition(array $definition): void
    {
        $this->dagParser->parse($definition);
    }

    public function listForTenant(string $tenantId, array $filters = []): LengthAwarePaginator
    {
        $query = Workflow::forTenant($tenantId)
            ->with(['creator:id,name,email'])
            ->latest();

        if (!empty($filters['search'])) {
            $query->where(function ($q) use ($filters) {
                $q->where('name', 'ilike', "%{$filters['search']}%")
                    ->orWhere('description', 'ilike', "%{$filters['search']}%");
            });
        }

        if (!empty($filters['trigger_type'])) {
            $query->where('trigger_type', $filters['trigger_type']);
        }

        if (isset($filters['is_active'])) {
            // $query->where('is_active', $filters['is_active']);
        }

        if (!empty($filters['tags'])) {
            // $query->where('tags', '@>', json_encode($filters['tags']));
        }

        return $query->paginate($filters['per_page'] ?? 15);
    }
}
