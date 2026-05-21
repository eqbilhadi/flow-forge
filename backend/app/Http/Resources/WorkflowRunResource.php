<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowRunResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'workflow_id' => $this->workflow_id,
            'workflow' => $this->whenLoaded('workflow', fn () => [
                'id' => $this->workflow->id,
                'name' => $this->workflow->name,
            ]),
            'status' => $this->status->value,
            'trigger_type' => $this->trigger_type->value,
            'workflow_version' => $this->workflow_version,
            'triggered_by' => $this->whenLoaded('triggeredBy', fn () =>
                $this->triggeredBy ? ['id' => $this->triggeredBy->id, 'name' => $this->triggeredBy->name] : null
            ),
            'input_data' => $this->input_data,
            'output_data' => $this->output_data,
            'error_message' => $this->error_message,
            'duration_seconds' => $this->getDurationSeconds(),
            'started_at' => $this->started_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'timeout_at' => $this->timeout_at?->toISOString(),
            'step_runs' => $this->whenLoaded('stepRuns', fn () =>
                $this->stepRuns->map(fn ($s) => [
                    'id' => $s->id,
                    'step_id' => $s->step_id,
                    'step_name' => $s->step_name,
                    'step_type' => $s->step_type->value,
                    'status' => $s->status->value,
                    'retry_count' => $s->retry_count,
                    'duration_ms' => $s->getDurationMs(),
                    'error_message' => $s->error_message,
                    'started_at' => $s->started_at?->toISOString(),
                    'completed_at' => $s->completed_at?->toISOString(),
                ])
            ),
            'created_at' => $this->created_at->toISOString(),
        ];
    }
}
