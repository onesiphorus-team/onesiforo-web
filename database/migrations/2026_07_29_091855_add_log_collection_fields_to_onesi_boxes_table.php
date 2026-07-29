<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table): void {
            $table->boolean('log_collection_enabled')->default(true)->after('screenshot_interval_seconds');
            $table->unsignedSmallInteger('log_collection_interval_minutes')->default(60)->after('log_collection_enabled');
            $table->unsignedSmallInteger('log_collection_lines')->default(200)->after('log_collection_interval_minutes');
            $table->timestamp('last_log_requested_at')->nullable()->after('log_collection_lines');
        });
    }

    public function down(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table): void {
            $table->dropColumn([
                'log_collection_enabled',
                'log_collection_interval_minutes',
                'log_collection_lines',
                'last_log_requested_at',
            ]);
        });
    }
};
