<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Zoom meetings a box joins often or now and then (e.g. other congregations, groups),
     * offered to caregivers as one-tap choices next to the congregation meeting.
     */
    public function up(): void
    {
        Schema::create('zoom_meetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('onesi_box_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('zoom_url', 2048);
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index(['onesi_box_id', 'last_used_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('zoom_meetings');
    }
};
