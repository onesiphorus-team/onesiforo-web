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
        Schema::create('meeting_instances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('congregation_id')->constrained()->cascadeOnDelete();
            $table->string('type'); // MeetingType enum
            $table->dateTime('scheduled_at');
            $table->string('zoom_url');
            $table->string('status')->default('scheduled');
            $table->string('cancelled_reason')->nullable();
            $table->timestamps();

            $table->unique(['congregation_id', 'type', 'scheduled_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('meeting_instances');
    }
};
