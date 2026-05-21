<?php

namespace App\Events;

use App\Models\StepRun;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class StepStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public readonly StepRun $stepRun,
    ) {}

    public function broadcastOn(): array
    {
        return [
            new Channel("tenant.{$this->stepRun->tenant_id}"),
            new Channel("run.{$this->stepRun->workflow_run_id}"),
        ];
    }

    public function broadcastAs(): string
    {
        return 'step.updated';
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->stepRun->id,
            'workflow_run_id' => $this->stepRun->workflow_run_id,
            'step_id' => $this->stepRun->step_id,
            'step_name' => $this->stepRun->step_name,
            'step_type' => $this->stepRun->step_type,
            'status' => $this->stepRun->status->value,
            'retry_count' => $this->stepRun->retry_count,
            'started_at' => $this->stepRun->started_at?->toISOString(),
            'completed_at' => $this->stepRun->completed_at?->toISOString(),
            'error_message' => $this->stepRun->error_message,
            'duration_ms' => $this->stepRun->getDurationMs(),
        ];
    }
}
