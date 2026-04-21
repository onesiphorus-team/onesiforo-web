<?php

declare(strict_types=1);

use App\Enums\MeetingInstanceStatus;
use App\Enums\MeetingType;
use App\Models\Congregation;
use App\Models\MeetingInstance;

it('belongs to a congregation', function (): void {
    $congregation = Congregation::factory()->create();
    $instance = MeetingInstance::factory()->create(['congregation_id' => $congregation->id]);

    expect($instance->congregation->id)->toBe($congregation->id);
});

it('casts type and status correctly', function (): void {
    $instance = MeetingInstance::factory()->create([
        'type' => 'midweek',
        'status' => 'scheduled',
    ]);

    expect($instance->type)->toBe(MeetingType::Midweek);
    expect($instance->status)->toBe(MeetingInstanceStatus::Scheduled);
});

it('detects non-terminal status', function (): void {
    $instance = MeetingInstance::factory()->create(['status' => 'notified']);

    expect($instance->status->isTerminal())->toBeFalse();
});

it('scopes to non-terminal statuses', function (): void {
    MeetingInstance::factory()->create(['status' => 'scheduled']);
    MeetingInstance::factory()->create(['status' => 'completed']);

    expect(MeetingInstance::query()->nonTerminal()->count())->toBe(1);
});
