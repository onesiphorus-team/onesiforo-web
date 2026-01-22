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
        Schema::create('playback_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('onesi_box_id')->constrained()->cascadeOnDelete();
            $table->string('event', 20);
            $table->string('media_url', 2048);
            $table->string('media_type', 10);
            $table->unsignedInteger('position')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('created_at');

            $table->index('onesi_box_id');
            $table->index('created_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playback_events');
    }
};
