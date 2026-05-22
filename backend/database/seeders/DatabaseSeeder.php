<?php

namespace Database\Seeders;

use App\Enums\TriggerType;
use App\Enums\UserRole;
use App\Models\Tenant;
use App\Models\User;
use App\Models\Workflow;
use App\Models\WorkflowVersion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Demo tenant
        $tenant = Tenant::create([
            'name' => 'Demo Organization',
            'slug' => 'demo-org',
            'is_active' => true,
        ]);

        // Admin user
        $admin = User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Admin User',
            'email' => 'admin@demo.com',
            'password' => Hash::make('password'),
            'role' => UserRole::ADMIN,
            'is_active' => true,
        ]);

        // Editor user
        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Editor User',
            'email' => 'editor@demo.com',
            'password' => Hash::make('password'),
            'role' => UserRole::EDITOR,
            'is_active' => true,
        ]);

        // Viewer user
        User::create([
            'tenant_id' => $tenant->id,
            'name' => 'Viewer User',
            'email' => 'viewer@demo.com',
            'password' => Hash::make('password'),
            'role' => UserRole::VIEWER,
            'is_active' => true,
        ]);

        // Demo workflow
        $definition = [
            'steps' => [
                [
                    'id' => 'fetch_data',
                    'name' => 'Fetch API Data',
                    'type' => 'http',
                    'config' => [
                        'url' => 'https://jsonplaceholder.typicode.com/posts/1',
                        'method' => 'GET',
                    ],
                    'depends_on' => [],
                    'retry' => ['max_attempts' => 3, 'backoff' => 'exponential', 'base_delay_ms' => 1000],
                ],
                [
                    'id' => 'wait_a_moment',
                    'name' => 'Wait 10 seconds',
                    'type' => 'delay',
                    'config' => ['duration_ms' => 10000],
                    'depends_on' => ['fetch_data'],
                ],
                [
                    'id' => 'echo_result',
                    'name' => 'Echo Result',
                    'type' => 'script',
                    'config' => ['script' => 'echo "Workflow completed successfully"'],
                    'depends_on' => ['wait_a_moment'],
                ],
            ],
        ];

        $workflow = Workflow::create([
            'tenant_id' => $tenant->id,
            'created_by' => $admin->id,
            'name' => 'Demo: Fetch & Process',
            'description' => 'A sample workflow that fetches data, waits, then echoes a result.',
            'definition' => $definition,
            'version' => 1,
            'is_active' => true,
            'trigger_type' => TriggerType::MANUAL,
            'timeout_seconds' => 300,
            'tags' => ['demo', 'sample'],
        ]);

        WorkflowVersion::create([
            'workflow_id' => $workflow->id,
            'tenant_id' => $tenant->id,
            'version' => 1,
            'definition' => $definition,
            'created_by' => $admin->id,
            'change_notes' => 'Initial version',
        ]);

        $this->command->info('✅ Demo data seeded successfully!');
        $this->command->info('   Admin: admin@demo.com / password');
        $this->command->info('   Editor: editor@demo.com / password');
        $this->command->info('   Viewer: viewer@demo.com / password');
    }
}
