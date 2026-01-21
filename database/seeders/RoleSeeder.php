<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Oltrematica\RoleLite\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $roles = ['super-admin', 'admin', 'caregiver'];

        foreach ($roles as $roleName) {
            Role::query()->firstOrCreate(['name' => $roleName]);
        }
    }
}
