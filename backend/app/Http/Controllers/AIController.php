<?php

namespace App\Http\Controllers;

use App\Services\AIWorkflowBuilderService;
use App\Services\DAGParser;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AIController extends Controller
{
    public function __construct(
        private readonly AIWorkflowBuilderService $aiService,
        private readonly DAGParser $dagParser,
    ) {}

    public function generateWorkflow(Request $request): JsonResponse
    {
        $request->validate([
            'description' => 'required|string|min:10|max:2000',
        ]);

        $definition = $this->aiService->generateFromDescription($request->string('description'));

        return response()->json([
            'definition' => $definition,
            'step_count' => count($definition['steps']),
            'message' => 'Workflow definition generated. Review before saving.',
        ]);
    }

    public function validateDefinition(Request $request): JsonResponse
    {
        $request->validate([
            'definition' => 'required|array',
        ]);

        try {
            $batches = $this->dagParser->parse($request->input('definition'));
            $stepCount = array_sum(array_map('count', $batches));

            return response()->json([
                'valid' => true,
                'step_count' => $stepCount,
                'batch_count' => count($batches),
                'execution_order' => array_map(
                    fn ($batch) => array_column($batch, 'id'),
                    $batches
                ),
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'valid' => false,
                'errors' => [$e->getMessage()],
            ], 422);
        }
    }
}
