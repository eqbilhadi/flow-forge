<?php

namespace App\Http\Controllers;

use App\Enums\TriggerType;
use App\Http\Requests\Workflow\StoreWorkflowRequest;
use App\Http\Requests\Workflow\UpdateWorkflowRequest;
use App\Http\Resources\WorkflowResource;
use App\Http\Resources\WorkflowRunResource;
use App\Models\Workflow;
use App\Services\WorkflowService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class WorkflowController extends Controller
{
    public function __construct(
        private readonly WorkflowService $workflowService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $user = auth('api')->user();

        $workflows = $this->workflowService->listForTenant($user->tenant_id, [
            'search' => $request->query('search'),
            'trigger_type' => $request->query('trigger_type'),
            'is_active' => $request->boolean('is_active', null),
            'tags' => $request->query('tags'),
            'per_page' => $request->integer('per_page', 15),
        ]);

        return WorkflowResource::collection($workflows);
    }

    public function store(StoreWorkflowRequest $request): JsonResponse
    {
        $user = auth('api')->user();
        $workflow = $this->workflowService->create($user, $request->validated());

        return response()->json([
            'message' => 'Workflow created successfully.',
            'workflow' => new WorkflowResource($workflow),
        ], 201);
    }

    public function show(Workflow $workflow): JsonResponse
    {
        $this->authorizeForTenant($workflow);
        $workflow->load(['creator:id,name,email', 'versions' => fn ($q) => $q->latest()->limit(10)]);

        return response()->json(['workflow' => new WorkflowResource($workflow)]);
    }

    public function update(UpdateWorkflowRequest $request, Workflow $workflow): JsonResponse
    {
        $this->authorizeForTenant($workflow);
        $this->requireEditor();

        $user = auth('api')->user();
        $workflow = $this->workflowService->update($workflow, $user, $request->validated());

        return response()->json([
            'message' => 'Workflow updated successfully.',
            'workflow' => new WorkflowResource($workflow),
        ]);
    }

    public function destroy(Workflow $workflow): JsonResponse
    {
        $this->authorizeForTenant($workflow);
        $this->requireAdmin();

        $workflow->delete();

        return response()->json(['message' => 'Workflow deleted successfully.']);
    }

    public function rollback(Request $request, Workflow $workflow): JsonResponse
    {
        $this->authorizeForTenant($workflow);
        $this->requireEditor();

        $request->validate(['version' => 'required|integer|min:1']);

        $user = auth('api')->user();
        $workflow = $this->workflowService->rollback($workflow, $user, $request->integer('version'));

        return response()->json([
            'message' => "Workflow rolled back to v{$request->integer('version')}.",
            'workflow' => new WorkflowResource($workflow),
        ]);
    }

    public function versions(Workflow $workflow): JsonResponse
    {
        $this->authorizeForTenant($workflow);

        $versions = $workflow->versions()
            ->with('creator:id,name')
            ->latest()
            ->paginate(20);

        return response()->json($versions);
    }

    public function trigger(Request $request, Workflow $workflow): JsonResponse
    {
        $this->authorizeForTenant($workflow);
        $this->requireEditor();

        $request->validate([
            'input_data' => 'nullable|array',
        ]);

        $user = auth('api')->user();
        $run = $this->workflowService->trigger(
            workflow: $workflow,
            triggerType: TriggerType::MANUAL,
            inputData: $request->input('input_data', []),
            triggeredBy: $user->id,
        );

        return response()->json([
            'message' => 'Workflow triggered successfully.',
            'run' => new WorkflowRunResource($run),
        ], 202);
    }

    public function runs(Request $request, Workflow $workflow): AnonymousResourceCollection
    {
        $this->authorizeForTenant($workflow);

        $runs = $workflow->runs()
            ->with('triggeredBy:id,name')
            ->latest()
            ->paginate($request->integer('per_page', 20));

        return WorkflowRunResource::collection($runs);
    }

    private function authorizeForTenant(Workflow $workflow): void
    {
        if ($workflow->tenant_id !== auth('api')->user()->tenant_id) {
            abort(403, 'Access denied.');
        }
    }

    private function requireEditor(): void
    {
        if (!auth('api')->user()->canEdit()) {
            abort(403, 'Editor or Admin role required.');
        }
    }

    private function requireAdmin(): void
    {
        if (!auth('api')->user()->isAdmin()) {
            abort(403, 'Admin role required.');
        }
    }
}
