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
            $table->unsignedInteger('current_media_position')->nullable()->after('current_media_title');
            $table->unsignedInteger('current_media_duration')->nullable()->after('current_media_position');
            $table->string('current_meeting_url', 500)->nullable()->after('current_meeting_id');
            $table->timestamp('current_meeting_joined_at')->nullable()->after('current_meeting_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table): void {
            $table->dropColumn([
                'current_media_position',
                'current_media_duration',
                'current_meeting_url',
                'current_meeting_joined_at',
            ]);
        });
    }
};
