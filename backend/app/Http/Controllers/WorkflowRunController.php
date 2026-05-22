<?php
namespace App\Http\Controllers;

use App\Http\Resources\WorkflowRunResource;
use App\Models\WorkflowRun;
use App\Services\AIWorkflowBuilderService;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;

class WorkflowRunController extends Controller
{
    public function show(WorkflowRun $run): JsonResponse
    {
        if ($run->tenant_id !== auth('api')->user()->tenant_id) {
            abort(403);
        }

        $run->load(['workflow:id,name', 'stepRuns' => fn ($q) => $q->orderBy('created_at')]);

        return response()->json(['run' => new WorkflowRunResource($run)]);
    }

    public function cancel(WorkflowRun $run): JsonResponse
    {
        if ($run->tenant_id !== auth('api')->user()->tenant_id) {
            abort(403);
        }

        app(WorkflowService::class)->cancel($run);

        return response()->json(['message' => 'Workflow run cancelled.']);
    }

    public function logs(WorkflowRun $run): JsonResponse
    {
        if ($run->tenant_id !== auth('api')->user()->tenant_id) {
            abort(403);
        }

        $stepRuns = $run->stepRuns()->orderBy('created_at')->get();

        return response()->json([
            'run_id' => $run->id,
            'steps' => $stepRuns->map(fn ($s) => [
                'step_id' => $s->step_id,
                'step_name' => $s->step_name,
                'status' => $s->status->value,
                'duration_ms' => $s->getDurationMs(),
                'retry_count' => $s->retry_count,
                'logs' => $s->logs ?? [],
                'error' => $s->error_message,
            ]),
        ]);
    }

    public function analyzeFailure(WorkflowRun $run): JsonResponse
    {
        if ($run->tenant_id !== auth('api')->user()->tenant_id) {
            abort(403);
        }

        if ($run->status !== \App\Enums\WorkflowRunStatus::FAILED) {
            return response()->json(['message' => 'Run is not in a failed state.'], 422);
        }

        $failedStep = $run->stepRuns()
            ->where('status', \App\Enums\StepStatus::FAILED)
            ->first();

        $context = [
            'workflow_name' => $run->workflow->name ?? 'Unknown',
            'run_id' => $run->id,
            'error_message' => $run->error_message,
            'failed_step' => $failedStep ? [
                'name' => $failedStep->step_name,
                'type' => $failedStep->step_type->value,
                'error' => $failedStep->error_message,
                'retry_count' => $failedStep->retry_count,
                'logs' => $failedStep->logs,
            ] : null,
        ];

        $analysis = app(AIWorkflowBuilderService::class)->analyzeFailure($context);

        return response()->json(['analysis' => $analysis]);
    }
}
