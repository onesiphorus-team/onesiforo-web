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
            $table->unsignedTinyInteger('cpu_usage')->nullable()->after('last_system_info_at');
            $table->unsignedTinyInteger('memory_usage')->nullable()->after('cpu_usage');
            $table->unsignedTinyInteger('disk_usage')->nullable()->after('memory_usage');
            $table->decimal('temperature', 5, 2)->nullable()->after('disk_usage');
            $table->unsignedInteger('uptime')->nullable()->after('temperature');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table): void {
            $table->dropColumn([
                'cpu_usage',
                'memory_usage',
                'disk_usage',
                'temperature',
                'uptime',
            ]);
        });
    }
};
