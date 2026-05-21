<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use OpenAI\Laravel\Facades\OpenAI;

/**
 * AIWorkflowBuilderService
 *
 * Converts natural language descriptions into valid DAG workflow definitions
 * using OpenAI's API. Implements strict output validation and fallback handling.
 */
class AIWorkflowBuilderService
{
    private const MAX_TOKENS = 2000;
    private const MODEL = 'gpt-4o-mini';

    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a workflow definition generator for FlowForge, a workflow orchestration platform.

When given a description of a workflow, generate a valid JSON workflow definition.

RULES:
1. Return ONLY a valid JSON object, no markdown, no explanation.
2. The JSON must have exactly one top-level key: "steps" (an array).
3. Each step must have: id (string, snake_case), name (string), type (one of: http|script|delay|condition), config (object), depends_on (array of step ids).
4. HTTP steps: config must have url (valid URL) and method (GET|POST|PUT|PATCH|DELETE). Optional: headers (object), body (object), timeout (int seconds).
5. Script steps: config must have script (string, safe shell command: echo/date/printf/expr only).
6. Delay steps: config must have duration_ms (integer milliseconds).
7. Condition steps: must have condition (string expression like "{{steps.step_id.output.field}} == value"), on_true (step_id or null), on_false (step_id or null).
8. Each step can have retry: { max_attempts: int, backoff: "exponential"|"linear"|"fixed", base_delay_ms: int }.
9. depends_on must only reference step ids that already exist in the steps array (before this step).
10. No circular dependencies.
11. Use descriptive step names and snake_case ids.

EXAMPLE OUTPUT for "Fetch user data then send email":
{
  "steps": [
    {
      "id": "fetch_user_data",
      "name": "Fetch User Data",
      "type": "http",
      "config": { "url": "https://api.example.com/users/1", "method": "GET" },
      "depends_on": [],
      "retry": { "max_attempts": 3, "backoff": "exponential", "base_delay_ms": 1000 }
    },
    {
      "id": "send_email",
      "name": "Send Email Notification",
      "type": "http",
      "config": {
        "url": "https://api.mailservice.com/send",
        "method": "POST",
        "body": { "to": "{{steps.fetch_user_data.output.email}}", "subject": "Hello" }
      },
      "depends_on": ["fetch_user_data"]
    }
  ]
}
PROMPT;

    public function generateFromDescription(string $description): array
    {
        if (strlen($description) > 2000) {
            throw new Exception('Workflow description is too long. Please keep it under 2000 characters.');
        }

        $description = strip_tags(trim($description));

        try {
            $response = OpenAI::chat()->create([
                'model' => self::MODEL,
                'max_tokens' => self::MAX_TOKENS,
                'temperature' => 0.2, // Low temperature for deterministic output
                'messages' => [
                    ['role' => 'system', 'content' => self::SYSTEM_PROMPT],
                    ['role' => 'user', 'content' => "Generate a workflow definition for: {$description}"],
                ],
            ]);

            $content = $response->choices[0]->message->content ?? '';

            return $this->parseAndValidateResponse($content, $description);
        } catch (Exception $e) {
            Log::error('AI workflow generation failed', [
                'description' => $description,
                'error' => $e->getMessage(),
            ]);

            throw new Exception('Failed to generate workflow: ' . $e->getMessage());
        }
    }

    /**
     * Analyze a failed workflow run and suggest fixes.
     */
    public function analyzeFailure(array $runContext): string
    {
        $contextJson = json_encode($runContext, JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
Analyze this failed workflow run and provide a clear, actionable diagnosis and fix suggestion.
Focus on: the specific error, likely root cause, and concrete steps to fix it.
Keep response under 300 words, use plain text (no markdown).

Failed workflow context:
{$contextJson}
PROMPT;

        try {
            $response = OpenAI::chat()->create([
                'model' => self::MODEL,
                'max_tokens' => 500,
                'temperature' => 0.3,
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a workflow debugging expert. Be concise and precise.'],
                    ['role' => 'user', 'content' => $prompt],
                ],
            ]);

            return $response->choices[0]->message->content ?? 'Unable to analyze failure.';
        } catch (Exception $e) {
            Log::error('AI failure analysis failed', ['error' => $e->getMessage()]);
            return 'AI analysis unavailable. Check logs for details.';
        }
    }

    /**
     * Parse, clean, and validate the LLM's JSON response.
     */
    private function parseAndValidateResponse(string $content, string $originalDescription): array
    {
        // Strip markdown code blocks if present
        $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
        $content = preg_replace('/\s*```$/m', '', $content);
        $content = trim($content);

        // Attempt to extract JSON if wrapped in non-JSON text
        if (!str_starts_with($content, '{')) {
            preg_match('/\{[\s\S]+\}/', $content, $matches);
            $content = $matches[0] ?? $content;
        }

        $definition = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('AI generated invalid JSON: ' . json_last_error_msg());
        }

        // Structural validation
        if (!isset($definition['steps']) || !is_array($definition['steps'])) {
            throw new Exception('AI response missing required "steps" array.');
        }

        if (empty($definition['steps'])) {
            throw new Exception('AI generated an empty workflow with no steps.');
        }

        if (count($definition['steps']) > 50) {
            throw new Exception('AI generated too many steps (max 50).');
        }

        // Validate via DAGParser for full structural integrity
        $parser = app(DAGParser::class);
        $parser->parse($definition); // throws on invalid

        return $definition;
    }
}
