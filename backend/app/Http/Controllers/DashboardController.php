<?php

namespace App\Http\Controllers;

use App\Enums\WorkflowRunStatus;
use App\Http\Resources\WorkflowRunResource;
use App\Models\WorkflowRun;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function health(): JsonResponse
    {
        $user = auth('api')->user();
        $tenantId = $user->tenant_id;

        $last24h = now()->subHours(24);

        $runs = WorkflowRun::forTenant($tenantId)
            ->where('created_at', '>=', $last24h)
            ->get();

        $total = $runs->count();
        $success = $runs->where('status', WorkflowRunStatus::SUCCESS)->count();
        $failed = $runs->where('status', WorkflowRunStatus::FAILED)->count();
        $active = $runs->whereIn('status', [WorkflowRunStatus::PENDING, WorkflowRunStatus::RUNNING])->count();

        $avgDuration = $runs
            ->filter(fn ($r) => $r->getDurationSeconds() !== null)
            ->avg(fn ($r) => $r->getDurationSeconds());

        return response()->json([
            'period' => '24h',
            'active_runs' => $active,
            'total_runs' => $total,
            'success_count' => $success,
            'failed_count' => $failed,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 1) : null,
            'avg_duration_seconds' => $avgDuration ? round($avgDuration, 1) : null,
        ]);
    }

    public function recentRuns(): JsonResponse
    {
        $user = auth('api')->user();

        $runs = WorkflowRun::forTenant($user->tenant_id)
            ->with(['workflow:id,name', 'triggeredBy:id,name'])
            ->latest()
            ->limit(20)
            ->get();

        return WorkflowRunResource::collection($runs)->response();
    }
}
