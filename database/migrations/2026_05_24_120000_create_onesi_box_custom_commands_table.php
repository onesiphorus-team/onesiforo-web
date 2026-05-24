<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // dropIfExists is intentional: a previous run of this migration failed
        // mid-way on MySQL (auto-generated index name >64 chars) leaving an
        // orphan table without the index. Wipe and recreate cleanly.
        Schema::dropIfExists('onesi_box_custom_commands');

        Schema::create('onesi_box_custom_commands', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('onesi_box_id')->constrained('onesi_boxes')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('description', 500)->nullable();
            $table->string('script_name', 100);
            $table->json('static_args')->nullable();
            $table->string('icon', 100)->nullable();
            $table->integer('sort_order')->default(0);
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
            $table->softDeletes();

            // Explicit short index name to stay within MySQL's 64-char limit.
            $table->index(['onesi_box_id', 'is_enabled', 'sort_order'], 'oboxcc_box_enabled_sort_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onesi_box_custom_commands');
    }
};
