<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Align the activity_log schema with spatie/laravel-activitylog v5:
     * - Add attribute_changes JSON column (stores tracked model changes)
     * - Migrate existing attribute/old data from properties → attribute_changes
     * - Drop the deprecated batch_uuid column (batch system removed in v5)
     */
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_log', 'attribute_changes')) {
                $table->json('attribute_changes')->nullable()->after('causer_id');
            }
        });

        DB::table('activity_log')
            ->whereNotNull('properties')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $properties = json_decode((string) $row->properties, true) ?: [];
                    $changes = array_intersect_key($properties, array_flip(['attributes', 'old']));
                    $remaining = array_diff_key($properties, array_flip(['attributes', 'old']));

                    DB::table('activity_log')
                        ->where('id', $row->id)
                        ->update([
                            'attribute_changes' => empty($changes) ? null : json_encode($changes),
                            'properties' => empty($remaining) ? null : json_encode($remaining),
                        ]);
                }
            });

        if (Schema::hasColumn('activity_log', 'batch_uuid')) {
            Schema::table('activity_log', function (Blueprint $table): void {
                $table->dropColumn('batch_uuid');
            });
        }
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table): void {
            if (! Schema::hasColumn('activity_log', 'batch_uuid')) {
                $table->uuid('batch_uuid')->nullable()->after('properties');
            }
        });

        DB::table('activity_log')
            ->whereNotNull('attribute_changes')
            ->orderBy('id')
            ->chunkById(500, function ($rows): void {
                foreach ($rows as $row) {
                    $properties = json_decode((string) $row->properties, true) ?: [];
                    $attributeChanges = json_decode((string) $row->attribute_changes, true) ?: [];
                    $merged = array_merge($properties, $attributeChanges);

                    DB::table('activity_log')
                        ->where('id', $row->id)
                        ->update([
                            'properties' => empty($merged) ? null : json_encode($merged),
                        ]);
                }
            });

        Schema::table('activity_log', function (Blueprint $table): void {
            if (Schema::hasColumn('activity_log', 'attribute_changes')) {
                $table->dropColumn('attribute_changes');
            }
        });
    }
};
