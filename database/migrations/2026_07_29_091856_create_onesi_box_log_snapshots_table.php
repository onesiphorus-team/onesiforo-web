<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('onesi_box_log_snapshots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('onesi_box_id')
                ->constrained('onesi_boxes')
                ->cascadeOnDelete();
            $table->foreignId('command_id')
                ->nullable()
                ->constrained('commands')
                ->nullOnDelete();
            $table->string('source', 20)->default('scheduled');
            $table->timestamp('captured_at');
            $table->string('log_file')->nullable();
            $table->unsignedInteger('total_lines')->nullable();
            $table->unsignedInteger('returned_lines')->nullable();
            $table->json('lines');
            $table->text('error_message')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['onesi_box_id', 'captured_at'], 'obls_box_captured_idx');
            $table->index('captured_at', 'obls_captured_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('onesi_box_log_snapshots');
    }
};
