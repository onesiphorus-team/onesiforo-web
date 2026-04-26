# Activity Timeline — Design

**Status:** Approved 2026-04-26
**Sub-project of:** "Caregiver-facing dashboard improvements" (paired with the Telegram bot sub-project, designed separately).

## Goal

Give caregivers a quick, retrospective view of "what happened on the OnesiBox today" without leaving the dashboard detail page. Two questions the timeline must answer at a glance:

1. Did the recipient listen to or watch anything today?
2. Did the recipient join their scheduled meeting?

## Scope

In scope:
- Read-only widget inside `OnesiBoxDetail` (`/dashboard/{onesiBox}`).
- Today only: midnight Europe/Rome → now.
- Two data sources: aggregated `playback_events` sessions and `meeting_attendances` rows.
- Static rendering (no Echo). Refresh on page load / accordion open.
- Caregiver visibility gated through `OnesiBoxPolicy::view`.

Out of scope (deferred):
- Echo/realtime updates.
- Date range selector (today/week/month). Today-only is enough for v1.
- Command history (who pressed what).
- Cross-midnight session reconstruction (sessions started before midnight). Documented limitation, accepted.

## UX

- New accordion item, title **"Attività oggi"**, sits inside the same `<div class="md:grid md:grid-cols-2">` as the other accordions in `onesi-box-detail.blade.php`.
- Default closed (matches the surrounding pattern; users open on demand).
- Body: vertical list ordered by `startedAt` DESC (most recent first), one row per entry.
- Each row: icon + time range + label + optional metadata.
  - Audio playback: `speaker-wave` icon, range `HH:MM–HH:MM`, label = media title or fallback to host (e.g. `youtube.com`), metadata = pause count when > 0 ("1 pausa", "3 pause").
  - Video playback: `video-camera` icon, same shape as audio.
  - Meeting (Zoom): `phone` icon, range `HH:MM–HH:MM` (or `HH:MM–in corso`), label = meeting type (`Adunanza infrasettimanale` / `Adunanza fine settimana` / `Adunanza ad-hoc`), metadata = congregation name when present.
- In-progress entries (no end time) render as `HH:MM–in corso`.
- Empty state: italic muted text "Nessuna attività registrata oggi".
- All times rendered in `Europe/Rome` (consistent with v0.10.6 timezone fix). The aggregator returns timestamps as UTC `CarbonInterface`; the blade reads `$displayTimezone` (passed via `render()->with(['displayTimezone' => config('app.display_timezone', 'Europe/Rome')])`) and applies `->copy()->setTimezone($displayTimezone)->format('H:i')` for display. Same pattern as `ScreenshotsViewer`.

## Architecture

### Components

```
ActivityTimeline (Livewire)              ← UI shell
  └── ActivityTimelineAggregator         ← pure aggregation, testable in isolation
        ↓ reads from
        playback_events  +  meeting_attendances
        ↓ produces
        Collection<ActivityTimelineEntry>
```

### Files (new)

- `app/Livewire/Dashboard/Controls/ActivityTimeline.php` — Livewire component.
- `app/Services/ActivityTimelineAggregator.php` — service class with the aggregation algorithm.
- `app/Support/ActivityTimelineEntry.php` — readonly value object.
- `app/Enums/ActivityTimelineKind.php` — enum: `Playback` | `Meeting`.
- `resources/views/livewire/dashboard/controls/activity-timeline.blade.php` — view.
- `tests/Unit/Services/ActivityTimelineAggregatorTest.php` — aggregator tests.
- `tests/Feature/Livewire/Dashboard/Controls/ActivityTimelineTest.php` — Livewire integration test.

### Files (modified)

- `resources/views/livewire/dashboard/onesi-box-detail.blade.php` — insert one `<x-accordion-item title="Attività oggi">…</x-accordion-item>` block. **Placement**: inside the same `md:grid md:grid-cols-2` accordion grid, **immediately after `command-queue`** and before `meeting-schedule`. Visible to all caregivers (not gated by `isAdmin`). The Livewire child is rendered with `wire:key="activity-timeline-{{ $onesiBox->id }}"` to match the surrounding pattern.

### Value object

```php
final readonly class ActivityTimelineEntry
{
    public function __construct(
        public ActivityTimelineKind $kind,
        public CarbonInterface $startedAt,
        public ?CarbonInterface $endedAt,   // null = in progress
        public string $label,
        public string $iconName,
        public ?string $metadata = null,    // e.g. "2 pause", "Cappelle sul Tavo"
    ) {}
}
```

### Aggregator interface

```php
final class ActivityTimelineAggregator
{
    /**
     * @return SupportCollection<int, ActivityTimelineEntry>
     */
    public function forBox(OnesiBox $box, CarbonInterface $from, CarbonInterface $to): SupportCollection;
}
```

The Livewire component constructs `from = today_midnight_rome->utc()` and `to = now()` and passes them to the aggregator. The aggregator does **not** know about timezones — it accepts UTC bounds.

### Aggregation algorithm — playback

`PlaybackEventType` enum cases (verified): `Started`, `Paused`, `Resumed`, `Stopped`, `Completed`, `Error`. The `playback_events` table has no `media_title` column — the only string identifying media is `media_url`.

Pull `playback_events` for the box with `created_at >= $from AND created_at < $to`, ordered by `created_at` ASC.

Walk linearly:

```
$open = null
foreach ($events as $event):
    match ($event->event):
        Started => {
            if ($open) emit($open);
            $open = new PendingSession(start: $event->created_at, label: deriveLabel($event), mediaType: $event->media_type);
        }
        Paused, Resumed => {
            if ($open) $open->pauseCount++;
        }
        Stopped, Completed, Error => {
            if ($open) {
                $open->endedAt = $event->created_at;
                emit($open);
                $open = null;
            }
            // else: orphan close (started before $from) — skip
        }
end:
    if ($open) emit($open with endedAt = null);   // in-progress
```

`deriveLabel(PlaybackEvent $event): string` precedence:
1. `parse_url($event->media_url, PHP_URL_HOST)` when `media_url` is set and parseable to a host.
2. fallback `Riproduzione`.

Pause count > 0 produces metadata `"1 pausa"` / `"N pause"`.

Media kind icon:
- `media_type === 'audio'` → `speaker-wave`
- otherwise → `video-camera`

### Aggregation algorithm — meetings

Pull `meeting_attendances` for the box where the related `meeting_instance.scheduled_at >= $from AND < $to`, eager-loading `meetingInstance.congregation`.

For each attendance:
- Skip when `status === MeetingAttendanceStatus::Skipped`.
- `startedAt = attendance.joined_at ?? meeting_instance.scheduled_at` (fallback to scheduled time when not yet joined).
- `endedAt = attendance.left_at` (null when in progress).
- `label = match meeting_instance.type → enum label`.
- `metadata = meeting_instance.congregation?->name`.
- `iconName = 'phone'`.

### Merge & sort

Both lists merged into a single `SupportCollection`, sorted by `startedAt` DESC. No de-dup needed (independent sources).

### Authorization

The Livewire component runs `Gate::authorize('view', $this->onesiBox)` in `mount()`. The accordion in the blade is rendered unconditionally — Filament's policy gating naturally blocks non-caregivers because they cannot reach the OnesiBox detail page in the first place. Belt-and-suspenders: still authorize in mount.

### Performance

Per-box, per-day data volume is small:
- `playback_events`: ≤ a few hundred events on a heavy day, typically <50.
- `meeting_attendances`: 0–2 per day (midweek + weekend at most, plus optional ad-hoc).

PHP-side aggregation is O(N) over a small set. No DB-side grouping needed. The two queries are scoped by `onesi_box_id` and the `created_at`/`scheduled_at` range — composite indexes already exist on the FK column for `playback_events`, sufficient for this filter size.

## Tests

### `ActivityTimelineAggregatorTest` (Unit)

1. Empty inputs → empty collection.
2. Single completed playback (`started` → `ended`) → 1 entry, correct start/end, `pauseCount = 0`.
3. Playback with one pause+resume → 1 entry, metadata `"1 pausa"`.
4. Playback with two pauses → 1 entry, metadata `"2 pause"`.
5. Two consecutive sessions on the same media → 2 entries.
6. Two sessions interleaved with other media → 2 entries, labels distinct.
7. In-progress playback (`started`, no end) → entry with `endedAt = null`.
8. Orphan close event (`stopped` with no preceding `started` in window) → ignored.
9. Meeting joined+left → 1 meeting entry with full range.
10. Meeting joined, not yet left → 1 entry, `endedAt = null`.
11. Skipped meeting → not emitted.
12. Mixed playback + meeting → ordered by `startedAt` DESC.
13. Timezone boundary: events at `2026-04-26 23:30 UTC` (= 01:30 Rome del 27) **not** included when called for "today Rome = 26".
14. Meeting label resolves the enum (`Adunanza fine settimana`, `Adunanza infrasettimanale`, `Adunanza ad-hoc`).
15. Media label fallback chain: host (from `media_url`) > `Riproduzione`.
16. `Error` event closes an open session like `Stopped`/`Completed` (in v1 we don't surface the error reason).

### `ActivityTimelineTest` (Feature, Livewire)

1. Caregiver of the box sees the component rendered with entries.
2. User who is not a caregiver of the box → `mount` aborts via `Gate::authorize`.
3. Empty state: caregiver of a brand-new box sees "Nessuna attività registrata oggi".
4. Times are rendered in `Europe/Rome` (assert specific HH:MM string for a known UTC fixture).
5. Component re-renders correctly when `wire:click` opens the accordion (no errors). [Optional, may be covered by smoke test.]

## Quality gate

For each implementation step, **in this order**, before advancing:

```bash
composer refactor              # rector — may rewrite code
vendor/bin/pint --dirty --format agent
composer analyse               # phpstan, must report 0 errors
php artisan test --compact
```

All four must be green. This protocol applies to every commit on the implementation branch.

## Known limitations (accepted for v1)

- Sessions started before midnight Rome are not stitched into today's view. The post-midnight `paused`/`resumed`/`ended` events for those sessions are silently dropped (no preceding `started` in the window). Acceptable because the alternative (load the previous day's tail) doubles complexity for an edge case the caregiver already gets context for via the live HeroCard if the session is still going.
- No realtime updates. The accordion is closed by default and the live state is already covered by the HeroCard above.
- No filtering or search. The volume per day does not justify it.

## Future extensions (not in this spec)

- Date range selector (today / 7 days / 30 days).
- Echo-based realtime entries.
- Caregiver-action timeline (who pressed Stop/Volume/Nuovo).
- Visualization (bar chart of listening hours per day).

## Risks

Low. Pure read-only widget over existing tables. No migrations, no API changes, no auth surface added. Worst case: a bug in aggregation causes an empty or wrong list — visible immediately, no data loss.
