<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Custom commands that run by themselves whenever the box starts showing something
     * (e.g. switching the TV to the OnesiBox input), so nobody has to remember them.
     */
    public function up(): void
    {
        Schema::table('onesi_box_custom_commands', function (Blueprint $table): void {
            $table->boolean('runs_before_content')->default(false)->after('is_enabled');
        });
    }

    public function down(): void
    {
        Schema::table('onesi_box_custom_commands', function (Blueprint $table): void {
            $table->dropColumn('runs_before_content');
        });
    }
};
