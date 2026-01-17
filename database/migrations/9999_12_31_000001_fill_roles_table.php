<?php

declare(strict_types=1);

use App\Enums\Roles;
use Illuminate\Database\Migrations\Migration;
use Oltrematica\RoleLite\Models\Role;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (Roles::cases() as $role) {
            Role::query()->updateOrCreate(['name' => $role->value]);
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
