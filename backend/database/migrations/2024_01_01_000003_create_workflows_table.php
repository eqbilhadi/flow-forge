<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('tenant_id');
            $table->uuid('created_by');
            $table->string('name');
            $table->text('description')->nullable();
            $table->jsonb('definition'); // The full DAG definition
            $table->integer('version')->default(1);
            $table->boolean('is_active')->default(true);
            $table->string('trigger_type')->default('manual'); // manual|schedule|webhook
            $table->string('cron_expression')->nullable();
            $table->integer('timeout_seconds')->default(3600);
            $table->jsonb('tags')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->foreign('created_by')->references('id')->on('users');
            $table->index(['tenant_id', 'is_active']);
            $table->index(['tenant_id', 'trigger_type']);
            $table->index(['tenant_id', 'created_at']);
        });

        Schema::create('workflow_versions', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('workflow_id');
            $table->uuid('tenant_id');
            $table->uuid('created_by');
            $table->integer('version');
            $table->jsonb('definition');
            $table->string('change_notes')->nullable();
            $table->timestamps();

            $table->foreign('workflow_id')->references('id')->on('workflows')->cascadeOnDelete();
            $table->foreign('tenant_id')->references('id')->on('tenants')->cascadeOnDelete();
            $table->index(['workflow_id', 'version']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_versions');
        Schema::dropIfExists('workflows');
    }
};
