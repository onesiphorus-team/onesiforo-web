<?php

declare(strict_types=1);

use App\Models\Congregation;
use App\Models\OnesiBox;
use App\Models\Recipient;
use Carbon\Carbon;

it('can calculate next midweek meeting', function (): void {
    $congregation = Congregation::factory()->create([
        'midweek_day' => Carbon::WEDNESDAY, // 3
        'midweek_time' => '19:00',
        'timezone' => 'Europe/Rome',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-03-09 10:00', 'Europe/Rome')); // Monday

    $next = $congregation->nextMidweekMeeting();

    expect($next->dayOfWeek)->toBe(Carbon::WEDNESDAY);
    expect($next->format('H:i'))->toBe('19:00');
    expect($next->timezone->getName())->toBe('Europe/Rome');
});

it('can calculate next weekend meeting', function (): void {
    $congregation = Congregation::factory()->create([
        'weekend_day' => Carbon::SUNDAY, // 0
        'weekend_time' => '10:00',
        'timezone' => 'Europe/Rome',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-03-09 10:00', 'Europe/Rome')); // Monday

    $next = $congregation->nextWeekendMeeting();

    expect($next->dayOfWeek)->toBe(Carbon::SUNDAY);
    expect($next->format('H:i'))->toBe('10:00');
});

it('returns next meeting when today is meeting day but time has passed', function (): void {
    $congregation = Congregation::factory()->create([
        'midweek_day' => Carbon::MONDAY,
        'midweek_time' => '09:00',
        'timezone' => 'Europe/Rome',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-03-09 10:00', 'Europe/Rome')); // Monday 10:00, after 09:00

    $next = $congregation->nextMidweekMeeting();

    expect($next->isAfter(now('Europe/Rome')))->toBeTrue();
});

it('has many recipients', function (): void {
    $congregation = Congregation::factory()->create();
    $recipient = Recipient::factory()->create(['congregation_id' => $congregation->id]);

    expect($congregation->recipients)->toHaveCount(1);
    expect($congregation->recipients->first()->id)->toBe($recipient->id);
});

it('can get onesi boxes through recipients', function (): void {
    $congregation = Congregation::factory()->create();
    $recipient = Recipient::factory()->create(['congregation_id' => $congregation->id]);
    $box = OnesiBox::factory()->create(['recipient_id' => $recipient->id]);

    expect($congregation->onesiBoxes)->toHaveCount(1);
    expect($congregation->onesiBoxes->first()->id)->toBe($box->id);
});

it('can get the next upcoming meeting of any type', function (): void {
    $congregation = Congregation::factory()->create([
        'midweek_day' => Carbon::WEDNESDAY,
        'midweek_time' => '19:00',
        'weekend_day' => Carbon::SUNDAY,
        'weekend_time' => '10:00',
        'timezone' => 'Europe/Rome',
    ]);

    Carbon::setTestNow(Carbon::parse('2026-03-11 10:00', 'Europe/Rome')); // Wednesday morning

    $next = $congregation->nextMeeting();

    expect($next['type']->value)->toBe('midweek');
    expect($next['scheduled_at']->dayOfWeek)->toBe(Carbon::WEDNESDAY);
});

it('computes next meeting respecting the congregation timezone', function (): void {
    $congregation = Congregation::factory()->create([
        'midweek_day' => Carbon::WEDNESDAY,
        'midweek_time' => '19:00',
        'timezone' => 'America/New_York',
    ]);

    // In New York è ancora martedì 23:00, a Roma sono già le 05:00 di mercoledì.
    // La congregazione deve vedere l'orario nel proprio fuso: mercoledì 19:00 (NY).
    Carbon::setTestNow(Carbon::parse('2026-03-11 05:00', 'Europe/Rome'));

    $next = $congregation->nextMidweekMeeting();

    expect($next->timezone->getName())->toBe('America/New_York');
    expect($next->dayOfWeek)->toBe(Carbon::WEDNESDAY);
    expect($next->format('Y-m-d H:i'))->toBe('2026-03-11 19:00');
});
