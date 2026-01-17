<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\App;

// use Illuminate\Database\Console\Seeds\WithoutModelEvents;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        /**
         * Database Seeder is intended to be used only in local and test environments.
         *
         * In production, the database should be migrated and seeded using the migration files.
         */
        if (App::isProduction()) {
            return;
        }

        // Base shared data (MUST be seeded first)
        $this->call([
            UserSeeder::class,
        ]);
    }
}
