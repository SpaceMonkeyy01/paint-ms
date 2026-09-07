<?php

namespace Database\Seeders;

use App\Enums\Role;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Dev users (one per role) + the legacy import. Idempotent.
     */
    public function run(): void
    {
        foreach ([
            ['Admin', 'admin@pms.local', Role::Admin],
            ['Store Keeper', 'store@pms.local', Role::Store],
            ['Painter', 'painter@pms.local', Role::Painter],
        ] as [$name, $email, $role]) {
            User::firstOrCreate(
                ['email' => $email],
                ['name' => $name, 'password' => 'password', 'role' => $role, 'email_verified_at' => now()],
            );
        }

        $this->call(LegacyImportSeeder::class);
    }
}
