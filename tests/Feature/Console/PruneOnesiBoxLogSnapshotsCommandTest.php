<?php

declare(strict_types=1);

use App\Models\OnesiBox;
use App\Models\OnesiBoxLogSnapshot;

it('deletes snapshots older than the retention window', function (): void {
    $box = OnesiBox::factory()->create();

    $fresh = OnesiBoxLogSnapshot::factory()->for($box, 'onesiBox')->create([
        'captured_at' => now()->subDays(3),
    ]);
    $stale = OnesiBoxLogSnapshot::factory()->for($box, 'onesiBox')->create([
        'captured_at' => now()->subDays(20),
    ]);

    $this->artisan('onesibox:prune-log-snapshots', ['--days' => 14])->assertSuccessful();

    expect(OnesiBoxLogSnapshot::query()->whereKey($fresh->id)->exists())->toBeTrue()
        ->and(OnesiBoxLogSnapshot::query()->whereKey($stale->id)->exists())->toBeFalse();
});

it('trims snapshots beyond the per-box cap keeping the most recent ones', function (): void {
    $box = OnesiBox::factory()->create();

    foreach (range(1, 8) as $i) {
        OnesiBoxLogSnapshot::factory()->for($box, 'onesiBox')->create([
            'captured_at' => now()->subMinutes($i),
        ]);
    }

    $this->artisan('onesibox:prune-log-snapshots', ['--days' => 365, '--keep' => 3])
        ->assertSuccessful();

    $remaining = OnesiBoxLogSnapshot::query()->where('onesi_box_id', $box->id)->get();

    expect($remaining)->toHaveCount(3)
        ->and($remaining->min('captured_at')->diffInMinutes(now(), absolute: true))
        ->toBeLessThan(4);
});

it('keeps each box within its own cap', function (): void {
    $first = OnesiBox::factory()->create();
    $second = OnesiBox::factory()->create();

    foreach (range(1, 5) as $i) {
        OnesiBoxLogSnapshot::factory()->for($first, 'onesiBox')->create(['captured_at' => now()->subMinutes($i)]);
        OnesiBoxLogSnapshot::factory()->for($second, 'onesiBox')->create(['captured_at' => now()->subMinutes($i)]);
    }

    $this->artisan('onesibox:prune-log-snapshots', ['--days' => 365, '--keep' => 2])
        ->assertSuccessful();

    expect(OnesiBoxLogSnapshot::query()->where('onesi_box_id', $first->id)->count())->toBe(2)
        ->and(OnesiBoxLogSnapshot::query()->where('onesi_box_id', $second->id)->count())->toBe(2);
});

it('leaves boxes under the cap untouched', function (): void {
    $box = OnesiBox::factory()->create();
    OnesiBoxLogSnapshot::factory()->count(2)->for($box, 'onesiBox')->create();

    $this->artisan('onesibox:prune-log-snapshots', ['--days' => 365, '--keep' => 10])
        ->assertSuccessful();

    expect(OnesiBoxLogSnapshot::query()->where('onesi_box_id', $box->id)->count())->toBe(2);
});
