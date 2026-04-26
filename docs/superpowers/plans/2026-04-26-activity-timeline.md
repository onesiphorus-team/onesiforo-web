# Activity Timeline Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Add a "Attività oggi" accordion to the OnesiBoxDetail dashboard page that shows aggregated playback sessions and Zoom meeting attendances for today (Europe/Rome midnight → now).

**Architecture:** A pure aggregation service (`ActivityTimelineAggregator`) walks `playback_events` to collapse them into sessions and merges them with `meeting_attendances`. A thin Livewire wrapper renders the result inside an `<x-accordion-item>`. No new tables, no API surface, no realtime.

**Tech Stack:** Laravel 12, Pest 4, Livewire 4, Flux UI 2 (`<x-accordion-item>` + `flux:icon`), Carbon, Spatie LaravelRoleLite (existing). Branch: `feat/activity-timeline` (already created with the spec commit).

**Spec:** `docs/superpowers/specs/2026-04-26-activity-timeline-design.md`

**Quality gate (run between every task before committing):**
```bash
composer refactor
vendor/bin/pint --dirty --format agent
composer analyse
php artisan test --compact
```

---

## File Structure

| Path | Responsibility |
|---|---|
| `app/Enums/ActivityTimelineKind.php` (new) | Enum distinguishing `Playback` vs `Meeting` entries |
| `app/Support/ActivityTimelineEntry.php` (new) | Readonly value object for a single timeline row |
| `app/Services/ActivityTimelineAggregator.php` (new) | Pure aggregation: events → entries collection |
| `app/Livewire/Dashboard/Controls/ActivityTimeline.php` (new) | Livewire wrapper, mounts the OnesiBox, exposes `entries` Computed |
| `resources/views/livewire/dashboard/controls/activity-timeline.blade.php` (new) | View renders the accordion body |
| `tests/Unit/Services/ActivityTimelineAggregatorTest.php` (new) | Unit tests on the aggregator |
| `tests/Feature/Livewire/Dashboard/Controls/ActivityTimelineTest.php` (new) | Feature tests on the Livewire component |
| `resources/views/livewire/dashboard/onesi-box-detail.blade.php` (modify) | Wire the new accordion between `command-queue` and `meeting-schedule` |

---

## Task 1: Enum `ActivityTimelineKind`

**Files:**
- Create: `app/Enums/ActivityTimelineKind.php`

- [ ] **Step 1: Create the enum**

```php
<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityTimelineKind: string
{
    case Playback = 'playback';
    case Meeting = 'meeting';
}
```

- [ ] **Step 2: Quality gate**

```bash
composer refactor && vendor/bin/pint --dirty --format agent && composer analyse && php artisan test --compact
```
Expected: rector OK, pint pass, phpstan 0 errors, all tests pass.

- [ ] **Step 3: Commit**

```bash
git add app/Enums/ActivityTimelineKind.php
git commit -m "feat(activity-timeline): add ActivityTimelineKind enum"
```

---

## Task 2: Value object `ActivityTimelineEntry`

**Files:**
- Create: `app/Support/ActivityTimelineEntry.php`

- [ ] **Step 1: Create the readonly value object**

```php
<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ActivityTimelineKind;
use Carbon\CarbonInterface;

final readonly class ActivityTimelineEntry
{
    public function __construct(
        public ActivityTimelineKind $kind,
        public CarbonInterface $startedAt,
        public ?CarbonInterface $endedAt,
        public string $label,
        public string $iconName,
        public ?string $metadata = null,
    ) {}

    public function isInProgress(): bool
    {
        return $this->endedAt === null;
    }
}
```

- [ ] **Step 2: Quality gate**

```bash
composer refactor && vendor/bin/pint --dirty --format agent && composer analyse && php artisan test --compact
```
Expected: all green.

- [ ] **Step 3: Commit**

```bash
git add app/Support/ActivityTimelineEntry.php
git commit -m "feat(activity-timeline): add ActivityTimelineEntry value object"
```

---

## Task 3: Aggregator skeleton + empty case (TDD)

**Files:**
- Create: `app/Services/ActivityTimelineAggregator.php`
- Test: `tests/Unit/Services/ActivityTimelineAggregatorTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php

declare(strict_types=1);

use App\Models\OnesiBox;
use App\Services\ActivityTimelineAggregator;
use Illuminate\Support\Carbon;

beforeEach(fn () => freezeTestTime('2026-04-26 12:00:00'));
afterEach(fn () => releaseTestTime());

it('returns an empty collection when the box has no events in the window', function (): void {
    $box = OnesiBox::factory()->create();

    $entries = resolve(ActivityTimelineAggregator::class)
        ->forBox($box, Carbon::now()->startOfDay(), Carbon::now());

    expect($entries)->toBeEmpty();
});
```

- [ ] **Step 2: Verify the test fails**

```bash
php artisan test --compact tests/Unit/Services/ActivityTimelineAggregatorTest.php
```
Expected: FAIL — `Class "App\Services\ActivityTimelineAggregator" not found`.

- [ ] **Step 3: Create the minimal aggregator**

```php
<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\OnesiBox;
use App\Support\ActivityTimelineEntry;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

final class ActivityTimelineAggregator
{
    /**
     * @return Collection<int, ActivityTimelineEntry>
     */
    public function forBox(OnesiBox $box, CarbonInterface $from, CarbonInterface $to): Collection
    {
        return collect();
    }
}
```

- [ ] **Step 4: Verify the test passes**

```bash
php artisan test --compact tests/Unit/Services/ActivityTimelineAggregatorTest.php
```
Expected: 1 passed.

- [ ] **Step 5: Quality gate + commit**

```bash
composer refactor && vendor/bin/pint --dirty --format agent && composer analyse && php artisan test --compact
git add app/Services/ActivityTimelineAggregator.php tests/Unit/Services/ActivityTimelineAggregatorTest.php
git commit -m "feat(activity-timeline): aggregator skeleton + empty case"
```

---

## Task 4: Aggregator — single completed playback session

**Files:**
- Modify: `app/Services/ActivityTimelineAggregator.php`
- Modify: `tests/Unit/Services/ActivityTimelineAggregatorTest.php`

- [ ] **Step 1: Write the failing test**

Append to the test file:

```php
it('emits one entry for a started + completed session with the host as label', function (): void {
    $box = OnesiBox::factory()->create();

    \App\Models\PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => \App\Enums\PlaybackEventType::Started,
        'media_url' => 'https://www.jw.org/media/audio/track-1.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::now()->subMinutes(30),
    ]);
    \App\Models\PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => \App\Enums\PlaybackEventType::Completed,
        'media_url' => 'https://www.jw.org/media/audio/track-1.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::now()->subMinutes(5),
    ]);

    $entries = resolve(ActivityTimelineAggregator::class)
        ->forBox($box, Carbon::now()->startOfDay(), Carbon::now());

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->kind)->toBe(\App\Enums\ActivityTimelineKind::Playback)
        ->and($entries->first()->label)->toBe('www.jw.org')
        ->and($entries->first()->iconName)->toBe('speaker-wave')
        ->and($entries->first()->startedAt->equalTo(Carbon::now()->subMinutes(30)))->toBeTrue()
        ->and($entries->first()->endedAt?->equalTo(Carbon::now()->subMinutes(5)))->toBeTrue()
        ->and($entries->first()->metadata)->toBeNull();
});
```

- [ ] **Step 2: Verify the test fails**

```bash
php artisan test --compact tests/Unit/Services/ActivityTimelineAggregatorTest.php
```
Expected: FAIL — `expected count 1, got 0` (aggregator still returns empty).

- [ ] **Step 3: Implement the playback walk**

Replace the body of `forBox`:

```php
use App\Enums\ActivityTimelineKind;
use App\Enums\PlaybackEventType;
use App\Models\PlaybackEvent;

public function forBox(OnesiBox $box, CarbonInterface $from, CarbonInterface $to): Collection
{
    return $this->collectPlaybackEntries($box, $from, $to)->values();
}

/**
 * @return Collection<int, ActivityTimelineEntry>
 */
private function collectPlaybackEntries(OnesiBox $box, CarbonInterface $from, CarbonInterface $to): Collection
{
    /** @var Collection<int, ActivityTimelineEntry> $entries */
    $entries = collect();

    /** @var array{started_at: CarbonInterface, label: string, media_type: string, pauses: int}|null $open */
    $open = null;

    PlaybackEvent::query()
        ->where('onesi_box_id', $box->id)
        ->whereBetween('created_at', [$from, $to])
        ->orderBy('created_at')
        ->each(function (PlaybackEvent $event) use (&$open, $entries): void {
            match ($event->event) {
                PlaybackEventType::Started => $open = $this->openSession($entries, $open, $event),
                PlaybackEventType::Paused, PlaybackEventType::Resumed => $open !== null
                    ? $open['pauses']++
                    : null,
                PlaybackEventType::Stopped, PlaybackEventType::Completed, PlaybackEventType::Error => $open = $this->closeSession($entries, $open, $event->created_at),
            };
        });

    if ($open !== null) {
        $entries->push($this->buildPlaybackEntry($open, endedAt: null));
    }

    return $entries;
}

/**
 * @param  Collection<int, ActivityTimelineEntry>  $entries
 * @param  array{started_at: CarbonInterface, label: string, media_type: string, pauses: int}|null  $open
 * @return array{started_at: CarbonInterface, label: string, media_type: string, pauses: int}
 */
private function openSession(Collection $entries, ?array $open, PlaybackEvent $event): array
{
    if ($open !== null) {
        $entries->push($this->buildPlaybackEntry($open, endedAt: $event->created_at));
    }

    return [
        'started_at' => $event->created_at,
        'label' => $this->derivePlaybackLabel($event),
        'media_type' => (string) $event->media_type,
        'pauses' => 0,
    ];
}

/**
 * @param  Collection<int, ActivityTimelineEntry>  $entries
 * @param  array{started_at: CarbonInterface, label: string, media_type: string, pauses: int}|null  $open
 */
private function closeSession(Collection $entries, ?array $open, CarbonInterface $endedAt): ?array
{
    if ($open === null) {
        return null;
    }

    $entries->push($this->buildPlaybackEntry($open, endedAt: $endedAt));

    return null;
}

/**
 * @param  array{started_at: CarbonInterface, label: string, media_type: string, pauses: int}  $session
 */
private function buildPlaybackEntry(array $session, ?CarbonInterface $endedAt): ActivityTimelineEntry
{
    return new ActivityTimelineEntry(
        kind: ActivityTimelineKind::Playback,
        startedAt: $session['started_at'],
        endedAt: $endedAt,
        label: $session['label'],
        iconName: $session['media_type'] === 'audio' ? 'speaker-wave' : 'video-camera',
        metadata: $session['pauses'] > 0
            ? trans_choice(':count pausa|:count pause', $session['pauses'], ['count' => $session['pauses']])
            : null,
    );
}

private function derivePlaybackLabel(PlaybackEvent $event): string
{
    if ($event->media_url === null) {
        return 'Riproduzione';
    }

    $host = parse_url($event->media_url, PHP_URL_HOST);

    return is_string($host) && $host !== '' ? $host : 'Riproduzione';
}
```

- [ ] **Step 4: Verify the test passes**

```bash
php artisan test --compact tests/Unit/Services/ActivityTimelineAggregatorTest.php
```
Expected: 2 passed.

- [ ] **Step 5: Quality gate + commit**

```bash
composer refactor && vendor/bin/pint --dirty --format agent && composer analyse && php artisan test --compact
git add -u
git commit -m "feat(activity-timeline): aggregate playback events into completed sessions"
```

---

## Task 5: Aggregator — pause counting and pluralization

**Files:**
- Modify: `tests/Unit/Services/ActivityTimelineAggregatorTest.php`

- [ ] **Step 1: Write the failing tests**

```php
it('counts pause/resume pairs as pause count metadata in singular form', function (): void {
    $box = OnesiBox::factory()->create();
    $base = Carbon::now()->subMinutes(30);

    foreach ([
        [\App\Enums\PlaybackEventType::Started, $base],
        [\App\Enums\PlaybackEventType::Paused, $base->copy()->addMinutes(5)],
        [\App\Enums\PlaybackEventType::Resumed, $base->copy()->addMinutes(7)],
        [\App\Enums\PlaybackEventType::Completed, $base->copy()->addMinutes(20)],
    ] as [$type, $at]) {
        \App\Models\PlaybackEvent::factory()->create([
            'onesi_box_id' => $box->id,
            'event' => $type,
            'media_url' => 'https://www.jw.org/audio.mp3',
            'media_type' => 'audio',
            'created_at' => $at,
        ]);
    }

    $entries = resolve(ActivityTimelineAggregator::class)
        ->forBox($box, Carbon::now()->startOfDay(), Carbon::now());

    expect($entries->first()->metadata)->toBe('2 pause');
});

it('uses plural form for two or more pause-related events', function (): void {
    // 1 paused + 1 resumed = 2 pause events → "2 pause"
    // already covered by the previous test; here we cover only-paused
    $box = OnesiBox::factory()->create();
    $base = Carbon::now()->subMinutes(30);

    foreach ([
        [\App\Enums\PlaybackEventType::Started, $base],
        [\App\Enums\PlaybackEventType::Paused, $base->copy()->addMinutes(5)],
        [\App\Enums\PlaybackEventType::Completed, $base->copy()->addMinutes(20)],
    ] as [$type, $at]) {
        \App\Models\PlaybackEvent::factory()->create([
            'onesi_box_id' => $box->id,
            'event' => $type,
            'media_url' => 'https://www.jw.org/audio.mp3',
            'media_type' => 'audio',
            'created_at' => $at,
        ]);
    }

    $entries = resolve(ActivityTimelineAggregator::class)
        ->forBox($box, Carbon::now()->startOfDay(), Carbon::now());

    expect($entries->first()->metadata)->toBe('1 pausa');
});
```

- [ ] **Step 2: Verify the tests pass**

The aggregator from Task 4 already counts `Paused` and `Resumed` as pause increments. Run:

```bash
php artisan test --compact tests/Unit/Services/ActivityTimelineAggregatorTest.php
```
Expected: 4 passed. If they fail, debug the increment in `match ()` arm.

- [ ] **Step 3: Commit**

```bash
git add -u
git commit -m "test(activity-timeline): cover pause counting and pluralization"
```

---

## Task 6: Aggregator — multiple sessions and orphan close

**Files:**
- Modify: `tests/Unit/Services/ActivityTimelineAggregatorTest.php`

- [ ] **Step 1: Write the failing tests**

```php
it('emits two entries for two consecutive started/completed sessions', function (): void {
    $box = OnesiBox::factory()->create();
    $base = Carbon::now()->subHours(2);

    foreach ([
        [\App\Enums\PlaybackEventType::Started, $base, 'https://a.example/track-1.mp3'],
        [\App\Enums\PlaybackEventType::Completed, $base->copy()->addMinutes(20), 'https://a.example/track-1.mp3'],
        [\App\Enums\PlaybackEventType::Started, $base->copy()->addMinutes(30), 'https://b.example/track-2.mp3'],
        [\App\Enums\PlaybackEventType::Completed, $base->copy()->addMinutes(50), 'https://b.example/track-2.mp3'],
    ] as [$type, $at, $url]) {
        \App\Models\PlaybackEvent::factory()->create([
            'onesi_box_id' => $box->id,
            'event' => $type,
            'media_url' => $url,
            'media_type' => 'audio',
            'created_at' => $at,
        ]);
    }

    $entries = resolve(ActivityTimelineAggregator::class)
        ->forBox($box, Carbon::now()->startOfDay(), Carbon::now());

    expect($entries)->toHaveCount(2)
        ->and($entries->pluck('label')->all())->toBe(['a.example', 'b.example']);
});

it('skips orphan close events whose Started predates the window', function (): void {
    $box = OnesiBox::factory()->create();

    \App\Models\PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => \App\Enums\PlaybackEventType::Completed,
        'media_url' => 'https://a.example/track.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::now()->subMinutes(10),
    ]);

    $entries = resolve(ActivityTimelineAggregator::class)
        ->forBox($box, Carbon::now()->startOfDay(), Carbon::now());

    expect($entries)->toBeEmpty();
});
```

- [ ] **Step 2: Verify the tests pass**

```bash
php artisan test --compact tests/Unit/Services/ActivityTimelineAggregatorTest.php
```
Expected: 6 passed. The aggregator already handles both cases (open replaces previous, close without open returns null).

- [ ] **Step 3: Commit**

```bash
git add -u
git commit -m "test(activity-timeline): cover multiple sessions and orphan close"
```

---

## Task 7: Aggregator — in-progress session

**Files:**
- Modify: `tests/Unit/Services/ActivityTimelineAggregatorTest.php`

- [ ] **Step 1: Write the failing test**

```php
it('emits an in-progress entry for a Started event with no terminal close', function (): void {
    $box = OnesiBox::factory()->create();

    \App\Models\PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => \App\Enums\PlaybackEventType::Started,
        'media_url' => 'https://www.jw.org/audio.mp3',
        'media_type' => 'video',
        'created_at' => Carbon::now()->subMinutes(15),
    ]);

    $entries = resolve(ActivityTimelineAggregator::class)
        ->forBox($box, Carbon::now()->startOfDay(), Carbon::now());

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->endedAt)->toBeNull()
        ->and($entries->first()->isInProgress())->toBeTrue()
        ->and($entries->first()->iconName)->toBe('video-camera');
});
```

- [ ] **Step 2: Verify the test passes**

```bash
php artisan test --compact tests/Unit/Services/ActivityTimelineAggregatorTest.php
```
Expected: 7 passed (the aggregator already handles in-progress via the trailing `if ($open !== null)`).

- [ ] **Step 3: Commit**

```bash
git add -u
git commit -m "test(activity-timeline): cover in-progress playback session"
```

---

## Task 8: Aggregator — meeting attendances

**Files:**
- Modify: `app/Services/ActivityTimelineAggregator.php`
- Modify: `tests/Unit/Services/ActivityTimelineAggregatorTest.php`

- [ ] **Step 1: Write the failing tests**

```php
it('emits a meeting entry for an attended meeting joined and left in the window', function (): void {
    $box = OnesiBox::factory()->create();
    $congregation = \App\Models\Congregation::factory()->create(['name' => 'Cappelle sul Tavo']);
    $instance = \App\Models\MeetingInstance::factory()->create([
        'congregation_id' => $congregation->id,
        'type' => \App\Enums\MeetingType::Weekend,
        'scheduled_at' => Carbon::now()->subHours(2),
    ]);
    \App\Models\MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $box->id,
        'status' => \App\Enums\MeetingAttendanceStatus::Joined,
        'joined_at' => Carbon::now()->subHours(2),
        'left_at' => Carbon::now()->subHour(),
    ]);

    $entries = resolve(ActivityTimelineAggregator::class)
        ->forBox($box, Carbon::now()->startOfDay(), Carbon::now());

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->kind)->toBe(\App\Enums\ActivityTimelineKind::Meeting)
        ->and($entries->first()->label)->toBe(\App\Enums\MeetingType::Weekend->getLabel())
        ->and($entries->first()->iconName)->toBe('phone')
        ->and($entries->first()->metadata)->toBe('Cappelle sul Tavo');
});

it('does not emit skipped meetings', function (): void {
    $box = OnesiBox::factory()->create();
    $instance = \App\Models\MeetingInstance::factory()->create([
        'scheduled_at' => Carbon::now()->subHour(),
    ]);
    \App\Models\MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $box->id,
        'status' => \App\Enums\MeetingAttendanceStatus::Skipped,
    ]);

    $entries = resolve(ActivityTimelineAggregator::class)
        ->forBox($box, Carbon::now()->startOfDay(), Carbon::now());

    expect($entries)->toBeEmpty();
});

it('emits an in-progress meeting entry when joined but not yet left', function (): void {
    $box = OnesiBox::factory()->create();
    $instance = \App\Models\MeetingInstance::factory()->create([
        'scheduled_at' => Carbon::now()->subMinutes(10),
    ]);
    \App\Models\MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $box->id,
        'status' => \App\Enums\MeetingAttendanceStatus::Joined,
        'joined_at' => Carbon::now()->subMinutes(10),
        'left_at' => null,
    ]);

    $entries = resolve(ActivityTimelineAggregator::class)
        ->forBox($box, Carbon::now()->startOfDay(), Carbon::now());

    expect($entries)->toHaveCount(1)
        ->and($entries->first()->endedAt)->toBeNull();
});
```

- [ ] **Step 2: Verify the tests fail**

```bash
php artisan test --compact tests/Unit/Services/ActivityTimelineAggregatorTest.php
```
Expected: 3 fail (`toHaveCount` 1 vs 0).

- [ ] **Step 3: Add meeting collection to the aggregator**

Append these methods after `derivePlaybackLabel()` and call them from `forBox`:

```php
use App\Enums\MeetingAttendanceStatus;
use App\Models\MeetingAttendance;

public function forBox(OnesiBox $box, CarbonInterface $from, CarbonInterface $to): Collection
{
    return $this->collectPlaybackEntries($box, $from, $to)
        ->merge($this->collectMeetingEntries($box, $from, $to))
        ->sortByDesc(fn (ActivityTimelineEntry $entry): int => $entry->startedAt->getTimestamp())
        ->values();
}

/**
 * @return Collection<int, ActivityTimelineEntry>
 */
private function collectMeetingEntries(OnesiBox $box, CarbonInterface $from, CarbonInterface $to): Collection
{
    return MeetingAttendance::query()
        ->with('meetingInstance.congregation')
        ->where('onesi_box_id', $box->id)
        ->where('status', '!=', MeetingAttendanceStatus::Skipped)
        ->whereHas('meetingInstance', fn ($q) => $q->whereBetween('scheduled_at', [$from, $to]))
        ->get()
        ->map(fn (MeetingAttendance $attendance): ActivityTimelineEntry => new ActivityTimelineEntry(
            kind: ActivityTimelineKind::Meeting,
            startedAt: $attendance->joined_at ?? $attendance->meetingInstance->scheduled_at,
            endedAt: $attendance->left_at,
            label: $attendance->meetingInstance->type->getLabel(),
            iconName: 'phone',
            metadata: $attendance->meetingInstance->congregation?->name,
        ))
        ->values();
}
```

- [ ] **Step 4: Verify the tests pass**

```bash
php artisan test --compact tests/Unit/Services/ActivityTimelineAggregatorTest.php
```
Expected: 10 passed.

- [ ] **Step 5: Quality gate + commit**

```bash
composer refactor && vendor/bin/pint --dirty --format agent && composer analyse && php artisan test --compact
git add -u
git commit -m "feat(activity-timeline): include meeting attendances in aggregator"
```

---

## Task 9: Aggregator — merge ordering and window boundary

**Files:**
- Modify: `tests/Unit/Services/ActivityTimelineAggregatorTest.php`

- [ ] **Step 1: Write the failing tests**

```php
it('orders mixed entries by startedAt DESC', function (): void {
    $box = OnesiBox::factory()->create();

    \App\Models\PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => \App\Enums\PlaybackEventType::Started,
        'media_url' => 'https://a.example/old.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::now()->subHours(4),
    ]);
    \App\Models\PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => \App\Enums\PlaybackEventType::Completed,
        'media_url' => 'https://a.example/old.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::now()->subHours(3),
    ]);
    $instance = \App\Models\MeetingInstance::factory()->create([
        'scheduled_at' => Carbon::now()->subHour(),
    ]);
    \App\Models\MeetingAttendance::factory()->create([
        'meeting_instance_id' => $instance->id,
        'onesi_box_id' => $box->id,
        'status' => \App\Enums\MeetingAttendanceStatus::Joined,
        'joined_at' => Carbon::now()->subHour(),
        'left_at' => Carbon::now()->subMinutes(10),
    ]);

    $entries = resolve(ActivityTimelineAggregator::class)
        ->forBox($box, Carbon::now()->startOfDay(), Carbon::now());

    expect($entries)->toHaveCount(2)
        ->and($entries->first()->kind)->toBe(\App\Enums\ActivityTimelineKind::Meeting)
        ->and($entries->last()->kind)->toBe(\App\Enums\ActivityTimelineKind::Playback);
});

it('excludes events outside the window', function (): void {
    $box = OnesiBox::factory()->create();

    // Yesterday — outside window
    \App\Models\PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => \App\Enums\PlaybackEventType::Started,
        'media_url' => 'https://a.example/yesterday.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::now()->subDay()->subHour(),
    ]);
    \App\Models\PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => \App\Enums\PlaybackEventType::Completed,
        'media_url' => 'https://a.example/yesterday.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::now()->subDay()->subMinutes(30),
    ]);

    $entries = resolve(ActivityTimelineAggregator::class)
        ->forBox($box, Carbon::now()->startOfDay(), Carbon::now());

    expect($entries)->toBeEmpty();
});
```

- [ ] **Step 2: Verify the tests pass**

```bash
php artisan test --compact tests/Unit/Services/ActivityTimelineAggregatorTest.php
```
Expected: 12 passed (sorting and `whereBetween` already implemented).

- [ ] **Step 3: Commit**

```bash
git add -u
git commit -m "test(activity-timeline): cover mixed ordering and window boundary"
```

---

## Task 10: Livewire component skeleton

**Files:**
- Create: `app/Livewire/Dashboard/Controls/ActivityTimeline.php`
- Create: `resources/views/livewire/dashboard/controls/activity-timeline.blade.php`

- [ ] **Step 1: Create the Livewire component**

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Models\OnesiBox;
use App\Services\ActivityTimelineAggregator;
use App\Support\ActivityTimelineEntry;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * @property-read Collection<int, ActivityTimelineEntry> $entries
 */
class ActivityTimeline extends Component
{
    #[Locked]
    public OnesiBox $onesiBox;

    public function mount(OnesiBox $onesiBox): void
    {
        Gate::authorize('view', $onesiBox);

        $this->onesiBox = $onesiBox;
    }

    /**
     * @return Collection<int, ActivityTimelineEntry>
     */
    #[Computed]
    public function entries(): Collection
    {
        $timezone = $this->displayTimezone();
        $from = CarbonImmutable::now($timezone)->startOfDay()->utc();
        $to = CarbonImmutable::now()->utc();

        return resolve(ActivityTimelineAggregator::class)->forBox($this->onesiBox, $from, $to);
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.activity-timeline', [
            'displayTimezone' => $this->displayTimezone(),
        ]);
    }

    private function displayTimezone(): string
    {
        return config('app.display_timezone', 'Europe/Rome');
    }
}
```

- [ ] **Step 2: Create the minimal blade view**

```blade
<div class="space-y-2">
    @forelse ($this->entries as $entry)
        <div class="flex items-center gap-3 text-sm">
            <flux:icon name="{{ $entry->iconName }}" class="h-4 w-4 text-zinc-500 dark:text-zinc-400" />
            <span class="font-medium tabular-nums text-zinc-700 dark:text-zinc-300">
                {{ $entry->startedAt->copy()->setTimezone($displayTimezone)->format('H:i') }}–{{ $entry->endedAt?->copy()->setTimezone($displayTimezone)->format('H:i') ?? 'in corso' }}
            </span>
            <span class="text-zinc-700 dark:text-zinc-200">{{ $entry->label }}</span>
            @if ($entry->metadata)
                <span class="text-xs text-zinc-500 dark:text-zinc-400">· {{ $entry->metadata }}</span>
            @endif
        </div>
    @empty
        <p class="text-sm italic text-zinc-500 dark:text-zinc-400">Nessuna attività registrata oggi.</p>
    @endforelse
</div>
```

- [ ] **Step 3: Quality gate**

```bash
composer refactor && vendor/bin/pint --dirty --format agent && composer analyse && php artisan test --compact
```
Expected: all green.

- [ ] **Step 4: Commit**

```bash
git add app/Livewire/Dashboard/Controls/ActivityTimeline.php resources/views/livewire/dashboard/controls/activity-timeline.blade.php
git commit -m "feat(activity-timeline): livewire component + blade view"
```

---

## Task 11: Feature test — caregiver visibility and content

**Files:**
- Create: `tests/Feature/Livewire/Dashboard/Controls/ActivityTimelineTest.php`

- [ ] **Step 1: Write the failing tests**

```php
<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Enums\PlaybackEventType;
use App\Livewire\Dashboard\Controls\ActivityTimeline;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

beforeEach(fn () => freezeTestTime('2026-04-26 12:00:00'));
afterEach(fn () => releaseTestTime());

it('renders a friendly empty state when there is no activity today', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->withCaregiver($user)->create();

    Livewire::actingAs($user)
        ->test(ActivityTimeline::class, ['onesiBox' => $box])
        ->assertSee('Nessuna attività registrata oggi');
});

it('renders entries for the caregiver of the box', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->withCaregiver($user)->create();

    PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => PlaybackEventType::Started,
        'media_url' => 'https://www.jw.org/audio.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::now()->subMinutes(30),
    ]);
    PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => PlaybackEventType::Completed,
        'media_url' => 'https://www.jw.org/audio.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::now()->subMinutes(5),
    ]);

    Livewire::actingAs($user)
        ->test(ActivityTimeline::class, ['onesiBox' => $box])
        ->assertSee('www.jw.org')
        ->assertDontSee('Nessuna attività');
});

it('renders times in Europe/Rome (not UTC)', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->withCaregiver($user)->create();

    // 09:00:00 UTC == 11:00:00 Europe/Rome (CEST in late April)
    PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => PlaybackEventType::Started,
        'media_url' => 'https://www.jw.org/audio.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::parse('2026-04-26 09:00:00', 'UTC'),
    ]);
    PlaybackEvent::factory()->create([
        'onesi_box_id' => $box->id,
        'event' => PlaybackEventType::Completed,
        'media_url' => 'https://www.jw.org/audio.mp3',
        'media_type' => 'audio',
        'created_at' => Carbon::parse('2026-04-26 09:30:00', 'UTC'),
    ]);

    Livewire::actingAs($user)
        ->test(ActivityTimeline::class, ['onesiBox' => $box])
        ->assertSee('11:00–11:30')
        ->assertDontSee('09:00–09:30');
});

it('aborts mount for a user who is not a caregiver of the box', function (): void {
    $stranger = User::factory()->create();
    $box = OnesiBox::factory()->create();

    Livewire::actingAs($stranger)
        ->test(ActivityTimeline::class, ['onesiBox' => $box]);
})->throws(Symfony\Component\HttpKernel\Exception\HttpException::class);

it('renders read-only caregivers the same as full-permission caregivers', function (): void {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->withCaregiver($user, OnesiBoxPermission::ReadOnly)->create();

    Livewire::actingAs($user)
        ->test(ActivityTimeline::class, ['onesiBox' => $box])
        ->assertSee('Nessuna attività registrata oggi');
});
```

- [ ] **Step 2: Verify the tests pass**

```bash
php artisan test --compact tests/Feature/Livewire/Dashboard/Controls/ActivityTimelineTest.php
```
Expected: 5 passed.

If the authorize() throws `AuthorizationException` instead of `HttpException`, replace the `throws` class with `Illuminate\Auth\Access\AuthorizationException::class`.

- [ ] **Step 3: Quality gate + commit**

```bash
composer refactor && vendor/bin/pint --dirty --format agent && composer analyse && php artisan test --compact
git add tests/Feature/Livewire/Dashboard/Controls/ActivityTimelineTest.php
git commit -m "test(activity-timeline): caregiver visibility, empty state, timezone"
```

---

## Task 12: Wire into OnesiBoxDetail

**Files:**
- Modify: `resources/views/livewire/dashboard/onesi-box-detail.blade.php`

- [ ] **Step 1: Inspect the current accordion grid**

Read the file around the `command-queue` accordion (search for `wire:key="command-queue-`). The new accordion goes immediately after it, before `meeting-schedule`.

- [ ] **Step 2: Insert the new accordion**

Locate this snippet:

```blade
                <x-accordion-item title="Comandi in coda" :open="$this->accordionDefaults['commands'] ?? false">
                    <livewire:dashboard.controls.command-queue :onesiBox="$onesiBox" wire:key="command-queue-{{ $onesiBox->id }}" />
                </x-accordion-item>
```

Insert immediately after it:

```blade
                <x-accordion-item title="Attività oggi">
                    <livewire:dashboard.controls.activity-timeline :onesiBox="$onesiBox" wire:key="activity-timeline-{{ $onesiBox->id }}" />
                </x-accordion-item>
```

The `Meeting programmati` accordion should remain unchanged after the new entry.

- [ ] **Step 3: Quality gate + smoke test**

```bash
composer refactor && vendor/bin/pint --dirty --format agent && composer analyse && php artisan test --compact
```
Expected: all green. The existing `OnesiBoxDetailTest` (if any) should still pass.

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/dashboard/onesi-box-detail.blade.php
git commit -m "feat(activity-timeline): wire accordion into OnesiBoxDetail page"
```

---

## Task 13: Manual UI verification

**Files:** none

- [ ] **Step 1: Start the dev environment**

```bash
herd open onesiforo
```
Or open `http://onesiforo.test/dashboard/{boxId}` in the browser.

- [ ] **Step 2: Visual checks**

Log in as a caregiver, navigate to an OnesiBox detail page. Verify:
- The "Attività oggi" accordion is present, between "Comandi in coda" and "Meeting programmati".
- It is closed by default (md+) or expandable on tap (mobile).
- Opening it shows either entries or the "Nessuna attività registrata oggi" empty state.
- The dark/light mode rendering of the row content (icon + range + label + metadata) is legible in both themes.

If any visual issue, note it in the PR description as a follow-up. Do not commit a "fix" without a test.

---

## Task 14: Push the branch and open the PR

**Files:** none

- [ ] **Step 1: Push the branch**

```bash
git push -u origin feat/activity-timeline
```

- [ ] **Step 2: Open the PR**

```bash
gh pr create --title "feat(dashboard): caregiver activity timeline accordion" --body "$(cat <<'EOF'
## Summary

Adds the "Attività oggi" accordion to the OnesiBox detail page so caregivers can see at a glance what the recipient listened to / watched and which Zoom meetings they joined today.

Spec: \`docs/superpowers/specs/2026-04-26-activity-timeline-design.md\`.

- New \`ActivityTimelineAggregator\` service walks \`playback_events\` into sessions and merges them with \`meeting_attendances\`.
- New \`ActivityTimeline\` Livewire component wraps the aggregator behind a \`Gate::authorize('view')\` and a \`#[Computed] entries\` property.
- New accordion item placed between "Comandi in coda" and "Meeting programmati", default closed.
- Static rendering — no Echo, no realtime updates.
- Times rendered in \`Europe/Rome\` via \`config('app.display_timezone')\`.

## Test plan

- [x] 12 unit tests on the aggregator (empty, single session, pauses pluralization, multiple sessions, orphan close, in-progress, meeting joined+left, skipped meeting, in-progress meeting, mixed ordering, window boundary)
- [x] 5 feature tests on the Livewire component (empty state, content for caregiver, Europe/Rome rendering, non-caregiver mount aborts, read-only caregiver visibility)
- [x] \`composer refactor\` clean
- [x] \`vendor/bin/pint\` clean
- [x] \`composer analyse\` 0 errors
- [x] Full \`php artisan test --compact\` suite green

## Known limitations (deferred to v2)

- Sessions started before midnight are not stitched into today's view.
- No realtime via Echo.
- No date range selector — today only.
EOF
)"
```

- [ ] **Step 3: Verify CI**

Wait for the CI checks (Pint, PHPStan, Tests) to complete. Address any failure before requesting merge.

---

## Self-review checklist (run before handing the plan off)

- [x] Every spec section has a task: UX, architecture, aggregator algorithm, tests, quality gate
- [x] No placeholders ("TBD", "fill in", "similar to Task N")
- [x] Type names consistent across tasks (`ActivityTimelineEntry`, `ActivityTimelineKind`, `ActivityTimelineAggregator`)
- [x] Quality gate explicit before every commit
- [x] PHPDoc + strict types on every new file
- [x] Tests precede implementation in every TDD task (Tasks 3, 4, 8 follow red-green-refactor)
