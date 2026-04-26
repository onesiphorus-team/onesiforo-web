<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('appliance_screenshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('onesi_box_id')
                ->constrained('onesi_boxes')
                ->cascadeOnDelete();
            $table->timestamp('captured_at');
            $table->unsignedSmallInteger('width');
            $table->unsignedSmallInteger('height');
            $table->unsignedInteger('bytes');
            $table->string('storage_path', 512);
            $table->timestamp('created_at')->useCurrent();

            $table->index(['onesi_box_id', 'captured_at'], 'ascr_box_captured_idx');
            $table->index('captured_at', 'ascr_captured_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('appliance_screenshots');
    }
};
