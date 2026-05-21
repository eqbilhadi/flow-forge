<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_id');
            $table->uuid('tenant_id');
            $table->uuid('triggered_by')->nullable();
            $table->string('trigger_type'); // manual|schedule|webhook
            $table->string('status')->default('pending'); // pending|running|success|failed|timeout|cancelled
            $table->integer('workflow_version');
            $table->jsonb('workflow_definition'); // Snapshot at time of run
            $table->jsonb('input_data')->nullable();
            $table->jsonb('output_data')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('timeout_at')->nullable();
            $table->timestamps();

            $table->foreign('workflow_id')->references('id')->on('workflows')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('triggered_by')->references('id')->on('users')->nullOnDelete();

            // Critical indexes for performance
            $table->index(['tenant_id', 'status']);
            $table->index(['tenant_id', 'created_at']); // For 24h health panel queries
            $table->index(['workflow_id', 'created_at']);
            $table->index(['status', 'created_at']); // For scheduler lookups
        });

        Schema::create('step_runs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_run_id');
            $table->uuid('tenant_id');
            $table->string('step_id');
            $table->string('step_name');
            $table->string('step_type');
            $table->string('status')->default('pending');
            $table->jsonb('input_data')->nullable();
            $table->jsonb('output_data')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('retry_count')->default(0);
            $table->jsonb('logs')->nullable(); // Array of log strings — append-only strategy
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('workflow_run_id')->references('id')->on('workflow_runs')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();

            $table->index(['workflow_run_id', 'step_id']);
            $table->index(['workflow_run_id', 'status']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('webhook_tokens', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_id');
            $table->uuid('tenant_id');
            $table->string('token', 64)->unique();
            $table->string('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('workflow_id')->references('id')->on('workflows')->cascadeOnDelete();
            $table->index('token');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_tokens');
        Schema::dropIfExists('step_runs');
        Schema::dropIfExists('workflow_runs');
    }
};
