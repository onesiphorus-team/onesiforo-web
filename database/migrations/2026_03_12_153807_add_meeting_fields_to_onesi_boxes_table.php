<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table): void {
            if (! Schema::hasColumn('onesi_boxes', 'meeting_join_mode')) {
                $table->string('meeting_join_mode')->default('manual');
            }
            if (! Schema::hasColumn('onesi_boxes', 'meeting_notifications_enabled')) {
                $table->boolean('meeting_notifications_enabled')->default(true);
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table): void {
            $columns = array_filter(
                ['meeting_join_mode', 'meeting_notifications_enabled'],
                fn (string $column): bool => Schema::hasColumn('onesi_boxes', $column),
            );

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
