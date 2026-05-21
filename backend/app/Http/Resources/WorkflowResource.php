<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class WorkflowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'definition' => $this->definition,
            'version' => $this->version,
            'is_active' => $this->is_active,
            'trigger_type' => $this->trigger_type?->value,
            'cron_expression' => $this->cron_expression,
            'timeout_seconds' => $this->timeout_seconds,
            'tags' => $this->tags ?? [],
            'step_count' => count($this->definition['steps'] ?? []),
            'creator' => $this->whenLoaded('creator', fn () => [
                'id' => $this->creator->id,
                'name' => $this->creator->name,
                'email' => $this->creator->email,
            ]),
            'versions' => $this->whenLoaded('versions', fn () =>
                $this->versions->map(fn ($v) => [
                    'version' => $v->version,
                    'change_notes' => $v->change_notes,
                    'created_by' => $v->creator?->name,
                    'created_at' => $v->created_at->toISOString(),
                ])
            ),
            'last_run' => $this->whenLoaded('runs', fn () =>
                $this->runs->first() ? new WorkflowRunResource($this->runs->first()) : null
            ),
            'created_at' => $this->created_at->toISOString(),
            'updated_at' => $this->updated_at->toISOString(),
        ];
    }
}
