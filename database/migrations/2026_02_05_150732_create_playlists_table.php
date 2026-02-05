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
        Schema::create('playlists', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('onesi_box_id')->constrained('onesi_boxes')->cascadeOnDelete();
            $table->string('name', 255)->nullable();
            $table->string('source_type', 20);
            $table->string('source_url', 2048)->nullable();
            $table->boolean('is_saved')->default(false);
            $table->timestamps();

            $table->index(['onesi_box_id', 'is_saved']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('playlists');
    }
};
