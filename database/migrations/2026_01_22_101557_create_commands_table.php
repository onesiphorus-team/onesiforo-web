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
        Schema::create('commands', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('onesi_box_id')->constrained()->cascadeOnDelete();
            $table->string('type', 50);
            $table->json('payload')->nullable();
            $table->tinyInteger('priority')->default(3);
            $table->string('status', 20)->default('pending');
            $table->timestamp('expires_at');
            $table->timestamp('executed_at')->nullable();
            $table->string('error_code', 10)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['onesi_box_id', 'status']);
            $table->index('expires_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('commands');
    }
};
