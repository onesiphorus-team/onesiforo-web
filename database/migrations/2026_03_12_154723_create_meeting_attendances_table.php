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
        if (Schema::hasTable('meeting_attendances')) {
            return;
        }

        Schema::create('meeting_attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('meeting_instance_id')->constrained()->cascadeOnDelete();
            $table->foreignId('onesi_box_id')->constrained()->cascadeOnDelete();
            $table->string('join_mode'); // MeetingJoinMode enum
            $table->dateTime('joined_at')->nullable();
            $table->dateTime('left_at')->nullable();
            $table->string('status')->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('meeting_attendances');
    }
};
