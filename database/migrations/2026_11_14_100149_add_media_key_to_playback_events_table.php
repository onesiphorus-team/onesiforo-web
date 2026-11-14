<?php

declare(strict_types=1);

use App\Support\JwMediaKey;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('playback_events', function (Blueprint $table): void {
            $table->string('media_key', 191)->nullable()->after('media_type');
            $table->index(['onesi_box_id', 'media_key', 'created_at']);
        });

        DB::table('playback_events')
            ->select(['id', 'media_url'])
            ->orderBy('id')
            ->chunkById(500, function ($events): void {
                foreach ($events as $event) {
                    $mediaKey = JwMediaKey::fromUrl((string) $event->media_url);

                    if ($mediaKey !== null) {
                        DB::table('playback_events')->where('id', $event->id)->update(['media_key' => $mediaKey]);
                    }
                }
            });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('playback_events', function (Blueprint $table): void {
            $table->dropIndex(['onesi_box_id', 'media_key', 'created_at']);
            $table->dropColumn('media_key');
        });
    }
};
