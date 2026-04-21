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
        if (Schema::hasTable('congregations')) {
            return;
        }

        Schema::create('congregations', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('zoom_url');
            $table->tinyInteger('midweek_day'); // Carbon dayOfWeek: 0=Sunday, 6=Saturday
            $table->time('midweek_time');
            $table->tinyInteger('weekend_day');
            $table->time('weekend_time');
            $table->string('timezone')->default('Europe/Rome');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('congregations');
    }
};
