<?php

namespace Database\Seeders;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $tenantA = Tenant::create([
            'name' => 'UNIDA University',
            'slug' => 'unida-uni',
        ]);

        User::create([
            'tenant_id' => $tenantA->id,
            'name' => 'Eqtada Bilhadi',
            'email' => 'eqtada@unida.com',
            'password' => Hash::make('password123'),
        ]);

        $tenantB = Tenant::create([
            'name' => 'Mitra Kampus Jaya',
            'slug' => 'mitra-jaya',
        ]);

        User::create([
            'tenant_id' => $tenantB->id,
            'name' => 'Budi Utomo',
            'email' => 'budi@mitra.com',
            'password' => Hash::make('password123'),
        ]);
    }
}
