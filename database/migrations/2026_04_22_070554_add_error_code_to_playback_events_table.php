<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playback_events', function (Blueprint $table): void {
            $table->string('error_code', 10)->nullable()->after('error_message');
            $table->index('error_code');
        });
    }

    public function down(): void
    {
        Schema::table('playback_events', function (Blueprint $table): void {
            $table->dropIndex(['error_code']);
            $table->dropColumn('error_code');
        });
    }
};
