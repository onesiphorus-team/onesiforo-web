<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\Roles;
use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (User::query()->count() > 0) {
            return;
        }

        foreach (Roles::cases() as $role) {
            User::factory()->role($role)->create([
                'email' => str($role->value)->slug()->append('@onesiforo.test')->toString(),
                'password' => bcrypt('password'),
            ]);
        }
    }
}
