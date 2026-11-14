<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The activity log is always read newest-first. Without this index every
     * page of the admin table sorts the whole table to return ten rows.
     *
     * Production already had this index, created by hand, so the migration
     * only adds it where it is missing.
     */
    public function up(): void
    {
        if (Schema::hasIndex('activity_log', ['created_at'])) {
            return;
        }

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->index('created_at');
        });
    }

    public function down(): void
    {
        if (! Schema::hasIndex('activity_log', ['created_at'])) {
            return;
        }

        Schema::table('activity_log', function (Blueprint $table): void {
            $table->dropIndex(['created_at']);
        });
    }
};
