<?php

declare(strict_types=1);

use App\Models\OnesiBox;
use App\Models\Playlist;
use App\Models\PlaylistItem;

describe('items relation', function (): void {
    it('returns playlist items ordered by position regardless of insertion order', function (): void {
        $box = OnesiBox::factory()->create();
        $playlist = Playlist::factory()->forOnesiBox($box)->create();

        // Insert out of order to verify ordering, not insertion order, drives the result.
        PlaylistItem::factory()->create(['playlist_id' => $playlist->id, 'position' => 2]);
        PlaylistItem::factory()->create(['playlist_id' => $playlist->id, 'position' => 0]);
        PlaylistItem::factory()->create(['playlist_id' => $playlist->id, 'position' => 1]);

        $positions = $playlist->items()->pluck('position')->all();

        expect($positions)->toBe([0, 1, 2]);
    });
});

describe('onlySaved scope', function (): void {
    it('matches only playlists with is_saved=true', function (): void {
        $box = OnesiBox::factory()->create();
        $saved = Playlist::factory()->forOnesiBox($box)->create(['is_saved' => true]);
        $unsaved = Playlist::factory()->forOnesiBox($box)->create(['is_saved' => false]);

        $ids = Playlist::query()->onlySaved()->pluck('id')->all();

        expect($ids)->toContain($saved->id)
            ->and($ids)->not->toContain($unsaved->id);
    });
});
