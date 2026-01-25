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
            $table->string('current_media_url', 500)->nullable()->after('status');
            $table->string('current_media_type', 20)->nullable()->after('current_media_url');
            $table->string('current_media_title', 255)->nullable()->after('current_media_type');
            $table->string('current_meeting_id', 50)->nullable()->after('current_media_title');
            $table->unsignedTinyInteger('volume')->default(80)->after('current_meeting_id');
            $table->timestamp('last_system_info_at')->nullable()->after('volume');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table): void {
            $table->dropColumn([
                'current_media_url',
                'current_media_type',
                'current_media_title',
                'current_meeting_id',
                'volume',
                'last_system_info_at',
            ]);
        });
    }
};
