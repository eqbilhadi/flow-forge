<?php

namespace App\Services;

use Exception;
use Gemini\Laravel\Facades\Gemini;
use Illuminate\Support\Facades\Log;

/**
 * AIWorkflowBuilderService
 *
 * Menggunakan Google Gemini API (gratis) untuk generate workflow definition
 * dan menganalisis kegagalan workflow.
 */
class AIWorkflowBuilderService
{
    private const SYSTEM_PROMPT = <<<'PROMPT'
You are a workflow definition generator for FlowForge, a workflow orchestration platform.

When given a description of a workflow, generate a valid JSON workflow definition.

RULES:
1. Return ONLY a valid JSON object, no markdown, no explanation, no backticks.
2. The JSON must have exactly one top-level key: "steps" (an array).
3. Each step must have: id (string, snake_case), name (string), type (one of: http|script|delay|condition), config (object), depends_on (array of step ids).
4. HTTP steps: config must have url (valid URL) and method (GET|POST|PUT|PATCH|DELETE). Optional: headers (object), body (object), timeout (int seconds).
5. Script steps: config must have script (string, safe shell command: echo/date/printf/expr only).
6. Delay steps: config must have duration_ms (integer milliseconds).
7. Condition steps: must have condition (string expression like "{{steps.step_id.output.field}} == value"), on_true (step_id or null), on_false (step_id or null).
8. Each step can have retry: { max_attempts: int, backoff: "exponential"|"linear"|"fixed", base_delay_ms: int }.
9. depends_on must only reference step ids defined before this step.
10. No circular dependencies.
11. Use descriptive step names and snake_case ids.
PROMPT;

    public function generateFromDescription(string $description): array
    {
        if (strlen($description) > 2000) {
            throw new Exception('Deskripsi terlalu panjang. Maksimal 2000 karakter.');
        }

        $description = strip_tags(trim($description));

        // Cek API key
        $apiKey = config('gemini.api_key') ?? env('GEMINI_API_KEY');
        if (empty($apiKey)) {
            throw new Exception('GEMINI_API_KEY belum dikonfigurasi di file .env');
        }

        $prompt = self::SYSTEM_PROMPT . "\n\nGenerate a workflow definition for: " . $description;

        $maxRetries = 3;
        $attempt    = 0;

        while ($attempt < $maxRetries) {
            try {
                $result = Gemini::generativeModel(model: 'gemini-2.5-flash')
                    ->generateContent($prompt);

                $content = $result->text();
                return $this->parseAndValidateResponse($content, $description);

            } catch (Exception $e) {
                $attempt++;
                $message = $e->getMessage();

                Log::error('Gemini error detail', [   // <-- tambah ini
                    'message' => $message,
                    'attempt' => $attempt,
                    'class'   => get_class($e),
                ]);


                // Rate limit — tunggu dan retry
                if (str_contains($message, '429') || str_contains($message, 'quota') || str_contains($message, 'rate')) {
                    if ($attempt < $maxRetries) {
                        $wait = 5 * $attempt;
                        Log::warning("Gemini rate limit, retry {$attempt}/{$maxRetries} in {$wait}s");
                        sleep($wait);
                        continue;
                    }
                    throw new Exception('API sedang sibuk. Silakan tunggu beberapa detik lalu coba lagi.');
                }

                throw new Exception('Gagal generate workflow: ' . $message);
            }
        }

        throw new Exception('Gagal generate workflow setelah beberapa percobaan.');
    }

    public function analyzeFailure(array $runContext): string
    {
        $apiKey = config('gemini.api_key') ?? env('GEMINI_API_KEY');
        if (empty($apiKey)) {
            return $this->fallbackAnalysis($runContext);
        }

        $contextJson = json_encode($runContext, JSON_PRETTY_PRINT);

        $prompt = <<<PROMPT
Analyze this failed workflow run and provide a clear, actionable diagnosis in Bahasa Indonesia.
Focus on: specific error, likely root cause, and concrete fix steps.
Keep response under 300 words, plain text only (no markdown).

Failed workflow context:
{$contextJson}
PROMPT;

        try {
            $result  = Gemini::generativeModel(model: 'gemini-2.5-flash')
                ->generateContent($prompt);
            return $result->text() ?? $this->fallbackAnalysis($runContext);
        } catch (Exception $e) {
            Log::error('Gemini failure analysis failed', ['error' => $e->getMessage()]);
            return $this->fallbackAnalysis($runContext);
        }
    }

    private function parseAndValidateResponse(string $content, string $originalDescription): array
    {
        // Bersihkan markdown code block jika ada
        $content = preg_replace('/^```(?:json)?\s*/m', '', $content);
        $content = preg_replace('/\s*```$/m', '', $content);
        $content = trim($content);

        // Extract JSON jika ada text lain
        if (!str_starts_with($content, '{')) {
            preg_match('/\{[\s\S]+\}/', $content, $matches);
            $content = $matches[0] ?? $content;
        }

        $definition = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('AI menghasilkan JSON yang tidak valid: ' . json_last_error_msg());
        }

        if (!isset($definition['steps']) || !is_array($definition['steps'])) {
            throw new Exception('AI response tidak memiliki array "steps".');
        }

        if (empty($definition['steps'])) {
            throw new Exception('AI menghasilkan workflow tanpa steps.');
        }

        if (count($definition['steps']) > 50) {
            throw new Exception('AI menghasilkan terlalu banyak steps (maks 50).');
        }

        // Validasi via DAGParser
        $parser = app(DAGParser::class);
        $parser->parse($definition);

        return $definition;
    }

    private function fallbackAnalysis(array $context): string
    {
        $step       = $context['failed_step'] ?? null;
        $stepType   = $step['type'] ?? 'unknown';
        $stepError  = $step['error'] ?? $context['error_message'] ?? '';
        $retryCount = $step['retry_count'] ?? 0;

        $diagnosis  = "=== Analisis Kegagalan Workflow ===\n\n";
        $diagnosis .= "Step Gagal : " . ($step['name'] ?? 'Unknown') . " ({$stepType})\n";
        $diagnosis .= "Error      : {$stepError}\n";
        if ($retryCount > 0) {
            $diagnosis .= "Retry      : {$retryCount}x\n";
        }
        $diagnosis .= "\n";

        if (str_contains($stepError, 'Could not resolve host') || str_contains($stepError, 'cURL error 6')) {
            $diagnosis .= "Penyebab: Hostname tidak dapat di-resolve — URL salah atau tidak ada koneksi internet.\n\n";
            $diagnosis .= "Saran:\n1. Periksa URL — pastikan domain benar dan tidak ada typo.\n2. Pastikan server memiliki akses internet.\n3. Test dengan: curl <url>";
        } elseif (str_contains($stepError, '404')) {
            $diagnosis .= "Penyebab: Endpoint tidak ditemukan (HTTP 404).\n\n";
            $diagnosis .= "Saran:\n1. Periksa path URL.\n2. Cek dokumentasi API target.";
        } elseif (str_contains($stepError, '401') || str_contains($stepError, '403')) {
            $diagnosis .= "Penyebab: Autentikasi gagal (HTTP 401/403).\n\n";
            $diagnosis .= "Saran:\n1. Periksa header Authorization.\n2. Pastikan API key/token masih valid.";
        } elseif (str_contains($stepError, 'timeout') || str_contains($stepError, 'timed out')) {
            $diagnosis .= "Penyebab: Request timeout.\n\n";
            $diagnosis .= "Saran:\n1. Tambah nilai timeout di konfigurasi step.\n2. Periksa apakah server target lambat.";
        } elseif ($stepType === 'script') {
            $diagnosis .= "Penyebab: Script command gagal.\n\n";
            $diagnosis .= "Saran:\n1. Pastikan command ada di whitelist: echo, date, printf, expr.\n2. Periksa syntax script.";
        } else {
            $diagnosis .= "Penyebab: Error pada eksekusi step.\n\n";
            $diagnosis .= "Saran:\n1. Periksa konfigurasi step.\n2. Coba jalankan ulang.\n3. Tambahkan retry pada step.";
        }

        $diagnosis .= "\n\nNote: Tambahkan GEMINI_API_KEY di .env untuk analisis AI yang lebih detail.";
        return $diagnosis;
    }
}
