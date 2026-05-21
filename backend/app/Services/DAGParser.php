<?php

namespace App\Services;

use App\Enums\StepType;
use InvalidArgumentException;

/**
 * DAGParser
 *
 * Parses, validates, and topologically sorts workflow DAG definitions.
 * A workflow definition looks like:
 * {
 *   "steps": [
 *     {
 *       "id": "step-1",
 *       "name": "Fetch Data",
 *       "type": "http",
 *       "config": { "url": "...", "method": "GET" },
 *       "depends_on": [],
 *       "retry": { "max_attempts": 3, "backoff": "exponential", "base_delay_ms": 1000 },
 *       "condition": null
 *     },
 *     {
 *       "id": "step-2",
 *       "name": "Process",
 *       "type": "script",
 *       "config": { "script": "echo hello" },
 *       "depends_on": ["step-1"]
 *     }
 *   ]
 * }
 */
class DAGParser
{
    private array $errors = [];

    /**
     * Parse and validate a workflow definition, returning ordered steps.
     *
     * @throws InvalidArgumentException
     */
    public function parse(array $definition): array
    {
        $this->errors = [];

        $steps = $definition['steps'] ?? [];

        if (empty($steps)) {
            throw new InvalidArgumentException('Workflow must have at least one step.');
        }

        $this->validateSteps($steps);

        if (!empty($this->errors)) {
            throw new InvalidArgumentException(implode('; ', $this->errors));
        }

        $this->detectCycles($steps);

        $ordered = $this->topologicalSort($steps);

        return $ordered;
    }

    /**
     * Validate individual step structure and types.
     */
    private function validateSteps(array $steps): void
    {
        $ids = array_column($steps, 'id');
        $uniqueIds = array_unique($ids);

        if (count($ids) !== count($uniqueIds)) {
            $this->errors[] = 'Duplicate step IDs detected.';
        }

        $idSet = array_flip($ids);

        foreach ($steps as $index => $step) {
            $prefix = "Step[{$index}]";

            if (empty($step['id'])) {
                $this->errors[] = "{$prefix}: 'id' is required.";
            }

            if (empty($step['name'])) {
                $this->errors[] = "{$prefix}: 'name' is required.";
            }

            if (empty($step['type'])) {
                $this->errors[] = "{$prefix}: 'type' is required.";
            } else {
                $validTypes = array_column(StepType::cases(), 'value');
                if (!in_array($step['type'], $validTypes)) {
                    $this->errors[] = "{$prefix}: Invalid type '{$step['type']}'. Allowed: " . implode(', ', $validTypes);
                }
            }

            $this->validateStepConfig($step, $prefix);

            // Validate depends_on references
            foreach ($step['depends_on'] ?? [] as $dep) {
                if (!isset($idSet[$dep])) {
                    $this->errors[] = "{$prefix}: Dependency '{$dep}' does not exist.";
                }

                if ($dep === ($step['id'] ?? null)) {
                    $this->errors[] = "{$prefix}: Step cannot depend on itself.";
                }
            }

            // Validate retry config
            if (isset($step['retry'])) {
                $retry = $step['retry'];
                if (isset($retry['max_attempts']) && (!is_int($retry['max_attempts']) || $retry['max_attempts'] < 1)) {
                    $this->errors[] = "{$prefix}: retry.max_attempts must be a positive integer.";
                }
                if (isset($retry['backoff']) && !in_array($retry['backoff'], ['linear', 'exponential', 'fixed'])) {
                    $this->errors[] = "{$prefix}: retry.backoff must be 'linear', 'exponential', or 'fixed'.";
                }
            }
        }
    }

    /**
     * Validate step-type-specific config
     */
    private function validateStepConfig(array $step, string $prefix): void
    {
        $config = $step['config'] ?? [];
        $type = $step['type'] ?? null;

        match ($type) {
            'http' => $this->validateHttpConfig($config, $prefix),
            'script' => $this->validateScriptConfig($config, $prefix),
            'delay' => $this->validateDelayConfig($config, $prefix),
            'condition' => $this->validateConditionConfig($step, $prefix),
            default => null,
        };
    }

    private function validateHttpConfig(array $config, string $prefix): void
    {
        if (empty($config['url'])) {
            $this->errors[] = "{$prefix}: HTTP step requires config.url.";
        }
        if (!empty($config['url']) && !filter_var($config['url'], FILTER_VALIDATE_URL)) {
            $this->errors[] = "{$prefix}: config.url is not a valid URL.";
        }
        if (!empty($config['method'])) {
            $allowed = ['GET', 'POST', 'PUT', 'PATCH', 'DELETE'];
            if (!in_array(strtoupper($config['method']), $allowed)) {
                $this->errors[] = "{$prefix}: config.method must be one of " . implode(', ', $allowed);
            }
        }
    }

    private function validateScriptConfig(array $config, string $prefix): void
    {
        if (empty($config['script'])) {
            $this->errors[] = "{$prefix}: Script step requires config.script.";
        }
    }

    private function validateDelayConfig(array $config, string $prefix): void
    {
        if (!isset($config['duration_ms'])) {
            $this->errors[] = "{$prefix}: Delay step requires config.duration_ms.";
        } elseif (!is_int($config['duration_ms']) || $config['duration_ms'] < 0) {
            $this->errors[] = "{$prefix}: config.duration_ms must be a non-negative integer.";
        }
    }

    private function validateConditionConfig(array $step, string $prefix): void
    {
        if (empty($step['condition'])) {
            $this->errors[] = "{$prefix}: Condition step requires 'condition' expression.";
        }
        if (empty($step['on_true']) && empty($step['on_false'])) {
            $this->errors[] = "{$prefix}: Condition step requires at least one of 'on_true' or 'on_false'.";
        }
    }

    /**
     * Detect cycles using DFS coloring (white=0, gray=1, black=2).
     *
     * @throws InvalidArgumentException if a cycle is found
     */
    private function detectCycles(array $steps): void
    {
        $graph = [];
        foreach ($steps as $step) {
            $graph[$step['id']] = $step['depends_on'] ?? [];
        }

        $color = [];
        foreach (array_keys($graph) as $node) {
            $color[$node] = 0;
        }

        $dfs = null;
        $dfs = function (string $node, array &$color, array &$graph, array &$path) use (&$dfs): void {
            $color[$node] = 1; // visiting
            $path[] = $node;

            foreach ($graph[$node] as $dep) {
                if ($color[$dep] === 1) {
                    $cycleStart = array_search($dep, $path);
                    $cycle = array_slice($path, $cycleStart);
                    $cycle[] = $dep;
                    throw new InvalidArgumentException(
                        'Cycle detected in workflow DAG: ' . implode(' -> ', $cycle)
                    );
                }
                if ($color[$dep] === 0) {
                    $dfs($dep, $color, $graph, $path);
                }
            }

            array_pop($path);
            $color[$node] = 2; // done
        };

        foreach (array_keys($graph) as $node) {
            if ($color[$node] === 0) {
                $path = [];
                $dfs($node, $color, $graph, $path);
            }
        }
    }

    /**
     * Kahn's algorithm for topological sort.
     * Returns steps in execution order, grouped by parallel batches.
     *
     * @return array<int, array> — each element is a batch of steps that can run in parallel
     */
    public function topologicalSort(array $steps): array
    {
        $stepMap = [];
        $inDegree = [];
        $dependents = [];

        foreach ($steps as $step) {
            $id = $step['id'];
            $stepMap[$id] = $step;
            $inDegree[$id] = count($step['depends_on'] ?? []);
            $dependents[$id] = [];
        }

        foreach ($steps as $step) {
            foreach ($step['depends_on'] ?? [] as $dep) {
                $dependents[$dep][] = $step['id'];
            }
        }

        $batches = [];
        $queue = array_keys(array_filter($inDegree, fn ($d) => $d === 0));

        while (!empty($queue)) {
            $batch = [];

            foreach ($queue as $id) {
                $batch[] = $stepMap[$id];
            }

            $batches[] = $batch;
            $nextQueue = [];

            foreach ($queue as $id) {
                foreach ($dependents[$id] as $dep) {
                    $inDegree[$dep]--;
                    if ($inDegree[$dep] === 0) {
                        $nextQueue[] = $dep;
                    }
                }
            }

            $queue = $nextQueue;
        }

        return $batches;
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
