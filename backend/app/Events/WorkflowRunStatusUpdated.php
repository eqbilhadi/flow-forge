<?php

namespace App\Events;

use App\Models\WorkflowRun;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class WorkflowRunStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly WorkflowRun $run,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("tenant.{$this->run->tenant_id}"),
            new Channel("run.{$this->run->id}"),
            new Channel("workflow.{$this->run->workflow_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'run.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->run->id,
            'workflow_id' => $this->run->workflow_id,
            'status' => $this->run->status->value,
            'trigger_type' => $this->run->trigger_type->value,
            'started_at' => $this->run->started_at?->toISOString(),
            'completed_at' => $this->run->completed_at?->toISOString(),
            'duration_seconds' => $this->run->getDurationSeconds(),
            'error_message' => $this->run->error_message,
        ];
    }
}
