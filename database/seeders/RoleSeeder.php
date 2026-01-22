<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Roles;
use Illuminate\Database\Seeder;
use Oltrematica\RoleLite\Models\Role;

class RoleSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        foreach (Roles::cases() as $role) {
            Role::query()->firstOrCreate(['name' => $role->value]);
        }
    }
}
