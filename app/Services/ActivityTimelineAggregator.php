<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\ActivityTimelineKind;
use App\Enums\MeetingAttendanceStatus;
use App\Enums\PlaybackEventType;
use App\Models\MeetingAttendance;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
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
        return $this->collectPlaybackEntries($box, $from, $to)
            ->merge($this->collectMeetingEntries($box, $from, $to))
            ->sortByDesc(fn (ActivityTimelineEntry $entry): int => $entry->startedAt->getTimestamp())
            ->values();
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

        $events = PlaybackEvent::query()
            ->where('onesi_box_id', $box->id)
            ->whereBetween('created_at', [$from, $to])
            ->oldest()
            ->get();

        foreach ($events as $event) {
            switch ($event->event) {
                case PlaybackEventType::Started:
                    if ($open !== null) {
                        $entries->push($this->buildPlaybackEntry($open, endedAt: $event->created_at));
                    }
                    $open = [
                        'started_at' => $event->created_at,
                        'label' => $this->derivePlaybackLabel($event),
                        'media_type' => (string) $event->media_type,
                        'pauses' => 0,
                    ];
                    break;

                case PlaybackEventType::Paused:
                case PlaybackEventType::Resumed:
                    if ($open !== null) {
                        $open['pauses']++;
                    }
                    break;

                case PlaybackEventType::Stopped:
                case PlaybackEventType::Completed:
                case PlaybackEventType::Error:
                    if ($open !== null) {
                        $entries->push($this->buildPlaybackEntry($open, endedAt: $event->created_at));
                        $open = null;
                    }
                    break;
            }
        }

        if ($open !== null) {
            $entries->push($this->buildPlaybackEntry($open, endedAt: null));
        }

        return $entries;
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
        if (in_array($event->media_url, [null, ''], true)) {
            return 'Riproduzione';
        }

        $host = parse_url((string) $event->media_url, PHP_URL_HOST);

        return is_string($host) && $host !== '' ? $host : 'Riproduzione';
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
                metadata: $attendance->meetingInstance->congregation->name,
            ))
            ->values();
    }
}
