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
        Schema::create('playback_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('onesi_box_id')->constrained('onesi_boxes')->cascadeOnDelete();
            $table->foreignId('playlist_id')->constrained('playlists')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->unsignedInteger('duration_minutes');
            $table->timestamp('started_at');
            $table->timestamp('ended_at')->nullable();
            $table->unsignedInteger('current_position')->default(0);
            $table->unsignedInteger('items_played')->default(0);
            $table->unsignedInteger('items_skipped')->default(0);
            $table->timestamps();

            $table->index(['onesi_box_id', 'status']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playback_sessions');
    }
};
