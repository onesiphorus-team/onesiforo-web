<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table) {
            $table->boolean('screenshot_enabled')->default(true)->after('is_active');
            $table->unsignedSmallInteger('screenshot_interval_seconds')->default(60)->after('screenshot_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table) {
            $table->dropColumn(['screenshot_enabled', 'screenshot_interval_seconds']);
        });
    }
};
