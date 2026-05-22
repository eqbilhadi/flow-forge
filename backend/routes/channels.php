<?php

use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

Broadcast::channel('tenant.{tenantId}', function ($user, $tenantId) {
    return $user->tenant_id === $tenantId;
});

Broadcast::channel('run.{runId}', function ($user, $runId) {
    $run = \App\Models\WorkflowRun::find($runId);
    return $run && $run->tenant_id === $user->tenant_id;
});

Broadcast::channel('workflow.{workflowId}', function ($user, $workflowId) {
    $workflow = \App\Models\Workflow::find($workflowId);
    return $workflow && $workflow->tenant_id === $user->tenant_id;
});
