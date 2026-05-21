<?php

namespace App\Jobs;

use App\Models\WorkflowRun;
use App\Services\WorkflowExecutionEngine;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class ExecuteWorkflowJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 7200; // 2 hours max
    public int $tries = 1;      // Engine handles retries internally per-step

    public function __construct(
        public readonly WorkflowRun $run,
    ) {}

    public function handle(WorkflowExecutionEngine $engine): void
    {
        $engine->execute($this->run);
    }

    public function failed(Throwable $exception): void
    {
        $this->run->update([
            'status' => 'failed',
            'error_message' => 'Job failed: ' . $exception->getMessage(),
            'completed_at' => now(),
        ]);
    }
}
