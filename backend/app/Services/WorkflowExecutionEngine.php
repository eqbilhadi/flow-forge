<?php

namespace App\Services;

use App\Enums\StepStatus;
use App\Enums\StepType;
use App\Enums\WorkflowRunStatus;
use App\Events\StepStatusUpdated;
use App\Events\WorkflowRunStatusUpdated;
use App\Models\StepRun;
use App\Models\WorkflowRun;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WorkflowExecutionEngine
{
    public function __construct(
        private readonly DAGParser $dagParser,
    ) {}

    /**
     * Execute a workflow run, processing all step batches.
     */
    public function execute(WorkflowRun $run): void
    {
        $this->updateRunStatus($run, WorkflowRunStatus::RUNNING, ['started_at' => now()]);

        try {
            $definition = $run->workflow_definition;
            $batches = $this->dagParser->topologicalSort($definition['steps'] ?? []);

            $contextData = $run->input_data ?? [];

            foreach ($batches as $batch) {
                // Check timeout
                if ($run->timeout_at && Carbon::now()->isAfter($run->timeout_at)) {
                    throw new Exception('Workflow execution timed out.');
                }

                // Execute batch in parallel (using concurrent HTTP or sequential for scripts)
                $batchResults = $this->executeBatch($run, $batch, $contextData);

                // Merge batch outputs into context
                foreach ($batchResults as $stepId => $result) {
                    $contextData['steps'][$stepId] = $result;
                }
            }

            $this->updateRunStatus($run, WorkflowRunStatus::SUCCESS, [
                'completed_at' => now(),
                'output_data' => $contextData,
            ]);
        } catch (Exception $e) {
            Log::error('WorkflowRun failed', [
                'run_id' => $run->id,
                'error' => $e->getMessage(),
            ]);

            $status = str_contains($e->getMessage(), 'timed out')
                ? WorkflowRunStatus::TIMEOUT
                : WorkflowRunStatus::FAILED;

            $this->updateRunStatus($run, $status, [
                'completed_at' => now(),
                'error_message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Execute a batch of steps that can run in parallel.
     */
    private function executeBatch(WorkflowRun $run, array $steps, array $contextData): array
    {
        $results = [];
        $failed = false;

        // In production this would use parallel processing (Fibers/async)
        // For MVP, run sequentially but track parallel-eligible steps
        foreach ($steps as $step) {
            try {
                $result = $this->executeStep($run, $step, $contextData);
                $results[$step['id']] = ['success' => true, 'output' => $result];
            } catch (Exception $e) {
                $results[$step['id']] = ['success' => false, 'error' => $e->getMessage()];
                $failed = true;
            }
        }

        if ($failed) {
            $failedSteps = array_filter($results, fn ($r) => !$r['success']);
            $errors = array_map(fn ($r) => $r['error'], $failedSteps);
            throw new Exception('Batch execution failed: ' . implode('; ', $errors));
        }

        return $results;
    }

    /**
     * Execute a single step with retry logic.
     */
    private function executeStep(WorkflowRun $run, array $step, array $contextData): mixed
    {
        $retryConfig = $step['retry'] ?? ['max_attempts' => 1, 'backoff' => 'exponential', 'base_delay_ms' => 1000];
        $maxAttempts = $retryConfig['max_attempts'] ?? 1;
        $attempt = 0;

        $stepRun = StepRun::create([
            'workflow_run_id' => $run->id,
            'tenant_id' => $run->tenant_id,
            'step_id' => $step['id'],
            'step_name' => $step['name'],
            'step_type' => $step['type'],
            'status' => StepStatus::PENDING,
            'input_data' => $this->resolveInputs($step['config'] ?? [], $contextData),
            'retry_count' => 0,
        ]);

        $this->broadcastStepUpdate($stepRun, StepStatus::RUNNING);
        $stepRun->update(['status' => StepStatus::RUNNING, 'started_at' => now()]);

        while ($attempt < $maxAttempts) {
            try {
                $output = $this->runStepType($step, $contextData, $stepRun);

                $stepRun->update([
                    'status' => StepStatus::SUCCESS,
                    'output_data' => $output,
                    'completed_at' => now(),
                ]);

                $this->broadcastStepUpdate($stepRun->fresh(), StepStatus::SUCCESS);

                return $output;
            } catch (Exception $e) {
                $attempt++;
                $stepRun->update(['retry_count' => $attempt]);

                if ($attempt >= $maxAttempts) {
                    $stepRun->update([
                        'status' => StepStatus::FAILED,
                        'error_message' => $e->getMessage(),
                        'completed_at' => now(),
                    ]);

                    $this->broadcastStepUpdate($stepRun->fresh(), StepStatus::FAILED);

                    throw new Exception("Step '{$step['name']}' failed after {$attempt} attempts: " . $e->getMessage());
                }

                // Exponential backoff
                $delayMs = $this->calculateBackoffDelay($attempt, $retryConfig);
                $stepRun->update(['status' => StepStatus::RETRYING]);
                $this->broadcastStepUpdate($stepRun->fresh(), StepStatus::RETRYING);

                usleep($delayMs * 1000);
            }
        }

        throw new Exception("Step '{$step['name']}' failed.");
    }

    /**
     * Dispatch to the correct step executor based on type.
     */
    private function runStepType(array $step, array $contextData, StepRun $stepRun): mixed
    {
        return match (StepType::from($step['type'])) {
            StepType::HTTP => $this->executeHttpStep($step, $contextData, $stepRun),
            StepType::SCRIPT => $this->executeScriptStep($step, $contextData, $stepRun),
            StepType::DELAY => $this->executeDelayStep($step, $stepRun),
            StepType::CONDITION => $this->executeConditionStep($step, $contextData, $stepRun),
        };
    }

    private function executeHttpStep(array $step, array $contextData, StepRun $stepRun): array
    {
        $config = $step['config'];
        $method = strtolower($config['method'] ?? 'get');
        $url = $this->interpolate($config['url'], $contextData);
        $headers = $config['headers'] ?? [];
        $body = $config['body'] ?? null;
        $timeout = $config['timeout'] ?? 30;

        $logs = ["Executing HTTP {$method} {$url}"];

        $response = Http::withHeaders($headers)
            ->timeout($timeout)
            ->$method($url, $body);

        if (!$response->successful()) {
            $logs[] = "HTTP error: {$response->status()}";
            $stepRun->update(['logs' => $logs]);
            throw new Exception("HTTP request failed with status {$response->status()}: {$response->body()}");
        }

        $logs[] = "HTTP success: {$response->status()}";
        $stepRun->update(['logs' => $logs]);

        return [
            'status_code' => $response->status(),
            'body' => $response->json() ?? $response->body(),
            'headers' => $response->headers(),
        ];
    }

    private function executeScriptStep(array $step, array $contextData, StepRun $stepRun): array
    {
        $config = $step['config'];
        $script = $this->interpolate($config['script'], $contextData);
        $timeout = $config['timeout'] ?? 60;

        $logs = ["Executing script: {$script}"];

        // SECURITY: In production, run in sandboxed container
        // For MVP, restricted shell execution
        $allowedCommands = ['echo', 'date', 'printf', 'expr'];
        $firstWord = explode(' ', trim($script))[0];

        if (!in_array($firstWord, $allowedCommands)) {
            throw new Exception("Script command '{$firstWord}' is not allowed. Allowed: " . implode(', ', $allowedCommands));
        }

        $escaped = escapeshellcmd($script);
        $output = shell_exec("timeout {$timeout} {$escaped} 2>&1");
        $exitCode = 0; // simplified

        $logs[] = "Script output: {$output}";
        $stepRun->update(['logs' => $logs]);

        return [
            'output' => trim($output ?? ''),
            'exit_code' => $exitCode,
        ];
    }

    private function executeDelayStep(array $step, StepRun $stepRun): array
    {
        $durationMs = $step['config']['duration_ms'];
        $logs = ["Delaying for {$durationMs}ms"];
        $stepRun->update(['logs' => $logs]);

        usleep($durationMs * 1000);

        return ['delayed_ms' => $durationMs];
    }

    private function executeConditionStep(array $step, array $contextData, StepRun $stepRun): array
    {
        $condition = $step['condition'];
        $result = $this->evaluateCondition($condition, $contextData);
        $branch = $result ? ($step['on_true'] ?? 'none') : ($step['on_false'] ?? 'none');

        $logs = ["Condition '{$condition}' evaluated to " . ($result ? 'true' : 'false') . ", branching to: {$branch}"];
        $stepRun->update(['logs' => $logs]);

        return [
            'condition_result' => $result,
            'branch' => $branch,
        ];
    }

    /**
     * Evaluate a simple condition expression against context data.
     * Supports: {{steps.stepId.output.field}} == "value" style expressions.
     */
    private function evaluateCondition(string $condition, array $contextData): bool
    {
        $resolved = $this->interpolate($condition, $contextData);

        // Simple equality check: "value1 == value2"
        if (preg_match('/^(.+?)\s*(==|!=|>|<|>=|<=)\s*(.+)$/', $resolved, $matches)) {
            $left = trim($matches[1], '"\'');
            $operator = $matches[2];
            $right = trim($matches[3], '"\'');

            return match ($operator) {
                '==' => $left == $right,
                '!=' => $left != $right,
                '>' => $left > $right,
                '<' => $left < $right,
                '>=' => $left >= $right,
                '<=' => $left <= $right,
                default => false,
            };
        }

        // Boolean-ish: "true" / "false"
        return in_array(strtolower($resolved), ['true', '1', 'yes']);
    }

    /**
     * Resolve {{variable}} placeholders using context data.
     */
    private function interpolate(string $template, array $contextData): string
    {
        return preg_replace_callback('/\{\{(.+?)\}\}/', function ($matches) use ($contextData) {
            $path = explode('.', trim($matches[1]));
            $value = $contextData;

            foreach ($path as $key) {
                if (!is_array($value) || !isset($value[$key])) {
                    return $matches[0]; // leave unresolved
                }
                $value = $value[$key];
            }

            return is_array($value) ? json_encode($value) : (string) $value;
        }, $template);
    }

    private function resolveInputs(array $config, array $contextData): array
    {
        $resolved = [];
        foreach ($config as $key => $value) {
            $resolved[$key] = is_string($value) ? $this->interpolate($value, $contextData) : $value;
        }
        return $resolved;
    }

    private function calculateBackoffDelay(int $attempt, array $retryConfig): int
    {
        $baseDelayMs = $retryConfig['base_delay_ms'] ?? 1000;
        $backoff = $retryConfig['backoff'] ?? 'exponential';

        return match ($backoff) {
            'exponential' => (int) ($baseDelayMs * pow(2, $attempt - 1)),
            'linear' => $baseDelayMs * $attempt,
            'fixed' => $baseDelayMs,
            default => $baseDelayMs,
        };
    }

    private function updateRunStatus(WorkflowRun $run, WorkflowRunStatus $status, array $extra = []): void
    {
        $run->update(array_merge(['status' => $status], $extra));
        $run->refresh();

        broadcast(new WorkflowRunStatusUpdated($run))->toOthers();
    }

    private function broadcastStepUpdate(StepRun $stepRun, StepStatus $status): void
    {
        broadcast(new StepStatusUpdated($stepRun))->toOthers();
    }
}
