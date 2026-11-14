<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fields behind the Telegram activity alerts (box turned on/off, meeting audio, playback).
     */
    public function up(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table): void {
            // 'online' / 'offline' as last announced: an alert is sent only when it changes.
            $table->string('connection_state', 10)->nullable()->after('last_seen_at');
            $table->boolean('activity_notifications_enabled')->default(true)->after('meeting_notifications_enabled');
            // Reported by the box during a Zoom call: false when Zoom stays without audio.
            $table->boolean('current_meeting_audio_connected')->nullable()->after('current_meeting_joined_at');
        });
    }

    public function down(): void
    {
        Schema::table('onesi_boxes', function (Blueprint $table): void {
            $table->dropColumn(['connection_state', 'activity_notifications_enabled', 'current_meeting_audio_connected']);
        });
    }
};
