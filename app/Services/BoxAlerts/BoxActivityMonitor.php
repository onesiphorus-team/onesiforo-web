<?php

declare(strict_types=1);

namespace App\Services\BoxAlerts;

use App\Enums\CommandType;
use App\Enums\MeetingAttendanceStatus;
use App\Enums\OnesiBoxStatus;
use App\Enums\PlaybackEventType;
use App\Models\Command;
use App\Models\CustomCommand;
use App\Models\MeetingAttendance;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
use App\Models\PlaybackSession;
use App\Models\ZoomMeeting;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Date;

/**
 * Turns what a box reports into Telegram alerts for the people looking after it.
 *
 * Only what matters is reported, once: the box turning on/off, entering and leaving a
 * meeting, Zoom without audio, the start and end of a content or a playlist (not every
 * item of a playlist), and playback stopping before its end.
 */
class BoxActivityMonitor
{
    /**
     * Problems of the same kind for the same box are reported at most once in this time.
     */
    public const int PROBLEM_QUIET_SECONDS = 10 * 60;

    /**
     * Starting several contents in a row (e.g. trying a few videos) is reported once in this time.
     */
    public const int START_QUIET_SECONDS = 2 * 60;

    /**
     * A playback that stops this close to its end is considered finished.
     */
    private const int END_TOLERANCE_SECONDS = 10;

    public function __construct(
        private readonly BoxActivityAlerts $alerts,
    ) {}

    /**
     * Called for each heartbeat, with the box state before and after it.
     *
     * @param  array{connection_state: string|null, last_seen_at: CarbonInterface|null, status: OnesiBoxStatus|null, meeting_url: string|null, meeting_joined_at: CarbonInterface|null, audio_connected: bool|null}  $before
     */
    public function heartbeatReceived(OnesiBox $onesiBox, array $before): void
    {
        if ($onesiBox->connection_state !== 'online') {
            $onesiBox->forceFill(['connection_state' => 'online'])->saveQuietly();
        }

        if ($before['connection_state'] === 'offline') {
            $this->announceBackOnline($onesiBox, $before);

            return;
        }

        $meetingUrl = $onesiBox->current_meeting_url;

        if ($before['meeting_url'] === null && $meetingUrl !== null) {
            $this->alerts->send($onesiBox, "🎥 Entrato nella riunione {$this->meetingName($onesiBox, $meetingUrl)}.");
        }

        if ($before['meeting_url'] !== null && $meetingUrl === null) {
            $this->announceMeetingLeft($onesiBox, $before);
        }

        if ($meetingUrl !== null) {
            $this->checkMeetingAudio($onesiBox, $before['audio_connected']);
        }
    }

    /**
     * Called every minute: a box silent for too long is announced as off.
     */
    public function connectionLost(OnesiBox $onesiBox): void
    {
        $onesiBox->forceFill(['connection_state' => 'offline'])->saveQuietly();

        $lastSeen = $onesiBox->last_seen_at?->copy()->setTimezone($this->timezone());
        $lines = ['🔴 Spento o non connesso'.($lastSeen !== null ? " (ultimo contatto alle {$lastSeen->format('H:i')})." : '.')];

        if ($onesiBox->status === OnesiBoxStatus::Calling && $onesiBox->current_meeting_url !== null) {
            $lines[] = "⚠️ Era collegato alla riunione {$this->meetingName($onesiBox, $onesiBox->current_meeting_url)}.";
        } elseif ($onesiBox->current_media_url !== null) {
            $lines[] = '⚠️ Stava mostrando {titolo}.';
        }

        $this->alerts->send($onesiBox, implode("\n", $lines), $onesiBox->current_media_url);
    }

    /**
     * Called a few minutes after a meeting started, for a box that should have joined it by itself
     * and is not in it. Sent once per meeting and box.
     */
    public function autoJoinMissed(MeetingAttendance $attendance): void
    {
        $attendance->loadMissing(['onesiBox.recipient', 'meetingInstance.congregation']);
        $onesiBox = $attendance->onesiBox;
        $meeting = $attendance->meetingInstance;

        $startedAt = $meeting->scheduled_at->copy()->setTimezone($meeting->congregation->timezone ?? $this->timezone());
        $lines = ['🚨 Adunanza '.mb_strtolower($meeting->type->getLabel())." iniziata alle {$startedAt->format('H:i')}, ma il box non è collegato."];

        if (! $onesiBox->isOnline()) {
            $lastSeen = $onesiBox->last_seen_at?->copy()->setTimezone($this->timezone());
            $lines[] = 'Il box è spento o non connesso'.($lastSeen !== null ? " (ultimo contatto alle {$lastSeen->format('H:i')})." : '.')
                .' Onesiforo lo collegherà da solo appena si riaccende.';
        } elseif ($attendance->status === MeetingAttendanceStatus::Pending) {
            $lines[] = 'Il collegamento automatico non è ancora partito: Onesiforo sta riprovando.';
        } else {
            $lines[] = 'Il comando di collegamento è stato inviato, ma il box non è entrato nella riunione.';
        }

        $lines[] = 'Per collegarlo: '.route('dashboard.show', $onesiBox).' › Zoom › Adunanza.';

        $this->alerts->send(
            $onesiBox,
            implode("\n", $lines),
            kind: "auto-join-missed:{$attendance->id}",
            quietSeconds: 24 * 60 * 60,
        );
    }

    /**
     * Called shortly before a meeting for a box in manual mode whose join nobody confirmed yet.
     * Sent once per meeting and box.
     */
    public function manualJoinNotConfirmed(MeetingAttendance $attendance): void
    {
        $attendance->loadMissing(['onesiBox.recipient', 'meetingInstance.congregation']);
        $onesiBox = $attendance->onesiBox;
        $meeting = $attendance->meetingInstance;

        $startsAt = $meeting->scheduled_at->copy()->setTimezone($meeting->congregation->timezone ?? $this->timezone());
        $lines = [
            '⏰ Adunanza '.mb_strtolower($meeting->type->getLabel())." alle {$startsAt->format('H:i')}: il box è in modalità manuale e nessuno ha ancora confermato il collegamento.",
        ];

        if (! $onesiBox->isOnline()) {
            $lines[] = '⚠️ In più il box risulta spento o non connesso.';
        }

        $lines[] = 'Per collegarlo: '.route('dashboard.show', $onesiBox).' › Adunanze › Conferma.';

        $this->alerts->send(
            $onesiBox,
            implode("\n", $lines),
            kind: "manual-join-unconfirmed:{$attendance->id}",
            quietSeconds: 24 * 60 * 60,
        );
    }

    /**
     * Called when the box reports that a custom script failed. Failures of the automatic
     * commands that prepare the box (e.g. to-box.sh switching the TV input) matter most: what
     * the box is showing may not be visible.
     */
    public function customScriptFailed(Command $command): void
    {
        $command->loadMissing('onesiBox');
        $payload = $command->payload ?? [];
        $scriptName = (string) ($payload['script_name'] ?? 'script');
        $customCommand = isset($payload['custom_command_id'])
            ? CustomCommand::query()->withTrashed()->whereKey($payload['custom_command_id'])->first()
            : null;
        $label = $customCommand !== null ? "«{$customCommand->name}» ({$scriptName})" : $scriptName;

        $lines = ($payload['automatic'] ?? false) === true
            ? ["📺 Il comando automatico {$label} non è riuscito: quello che il box sta mostrando potrebbe non vedersi sulla TV."]
            : ["⚠️ Il comando {$label} non è riuscito."];

        $lines[] = match ($command->error_code) {
            'E010' => 'Lo script non ha risposto entro 30 secondi (TV spenta o non raggiungibile?).',
            'E115' => 'Lo script non è installato sul box.',
            default => 'Motivo: '.($command->error_message ?: 'sconosciuto').'.',
        };

        $this->alerts->send(
            $command->onesiBox,
            implode("\n", $lines),
            kind: 'custom-script-failed:'.($payload['custom_command_id'] ?? $scriptName),
            quietSeconds: self::PROBLEM_QUIET_SECONDS,
        );
    }

    public function playbackEventReceived(OnesiBox $onesiBox, PlaybackEvent $playbackEvent): void
    {
        $session = $playbackEvent->session_id !== null
            ? PlaybackSession::query()->where('uuid', $playbackEvent->session_id)->first()
            : null;

        match ($playbackEvent->event) {
            PlaybackEventType::Started => $this->announceStart($onesiBox, $playbackEvent, $session),
            PlaybackEventType::Completed => $session === null ? $this->announceFinished($onesiBox, $playbackEvent) : null,
            PlaybackEventType::Error => $this->announcePlaybackProblem($onesiBox, $playbackEvent, 'per un errore'),
            PlaybackEventType::Stopped => $this->checkStop($onesiBox, $playbackEvent, $session),
            default => null,
        };
    }

    /**
     * @param  'completed'|'expired'|'stopped'  $reason
     */
    public function sessionEnded(PlaybackSession $session, string $reason): void
    {
        $session->loadMissing(['onesiBox', 'playlist']);
        $onesiBox = $session->onesiBox;
        $total = $session->playlist->items()->count();
        $shown = $session->items_played;
        $skipped = $session->items_skipped > 0 ? " ({$session->items_skipped} saltati per errore)" : '';
        $name = $session->playlist->name ?? 'Playlist';

        $text = match ($reason) {
            'completed' => "✅ {$name} finita: {$shown} di {$total} contenuti mostrati{$skipped}.",
            'expired' => "⏱️ {$name} finita perché è scaduto il tempo: {$shown} di {$total} contenuti mostrati{$skipped}.",
            'stopped' => "⏹️ {$name} fermata da Onesiforo dopo {$shown} di {$total} contenuti.",
        };

        $this->alerts->send($onesiBox, $text);
    }

    private function announceStart(OnesiBox $onesiBox, PlaybackEvent $playbackEvent, ?PlaybackSession $session): void
    {
        if ($session === null) {
            $this->alerts->send($onesiBox, '▶️ Sta mostrando {titolo}.', $playbackEvent->media_url, 'start', self::START_QUIET_SECONDS);

            return;
        }

        // A playlist is announced once, when its first item starts.
        if ($session->current_position !== 0 || $session->items_played > 0 || $session->items_skipped > 0) {
            return;
        }

        $session->loadMissing('playlist');

        $items = $session->playlist->items();
        $count = $items->count();
        $minutes = (int) ceil(((int) $items->sum('duration_seconds')) / 60);
        $duration = $minutes > 0 ? ' · circa '.$this->formatMinutes($minutes) : '';

        $this->alerts->send(
            $onesiBox,
            '▶️ '.($session->playlist->name ?? 'Playlist')." avviata: {$count} contenuti{$duration}.",
            kind: "session-start:{$session->uuid}",
            quietSeconds: 24 * 60 * 60,
        );
    }

    private function announceFinished(OnesiBox $onesiBox, PlaybackEvent $playbackEvent): void
    {
        $this->alerts->send($onesiBox, '✅ Finito {titolo}.', $playbackEvent->media_url);
    }

    /**
     * A "stopped" is a problem only when nobody asked for it and it came before the end.
     */
    private function checkStop(OnesiBox $onesiBox, PlaybackEvent $playbackEvent, ?PlaybackSession $session): void
    {
        if ($this->isNearTheEnd($playbackEvent)) {
            if ($session === null) {
                $this->announceFinished($onesiBox, $playbackEvent);
            }

            return;
        }

        if ($this->wasAskedToChange($onesiBox, $playbackEvent)) {
            return;
        }

        $where = $playbackEvent->position !== null && $playbackEvent->duration !== null
            ? ' (a '.$this->formatClock($playbackEvent->position).' di '.$this->formatClock($playbackEvent->duration).')'
            : '';

        $this->announcePlaybackProblem($onesiBox, $playbackEvent, "prima della fine{$where}");
    }

    private function announcePlaybackProblem(OnesiBox $onesiBox, PlaybackEvent $playbackEvent, string $why): void
    {
        $detail = filled($playbackEvent->error_message) ? "\nErrore: {$playbackEvent->error_message}" : '';

        $this->alerts->send(
            $onesiBox,
            "⚠️ Riproduzione di {titolo} interrotta {$why}.{$detail}",
            $playbackEvent->media_url,
            'playback-problem',
            self::PROBLEM_QUIET_SECONDS,
        );
    }

    /**
     * @param  array{connection_state: string|null, last_seen_at: CarbonInterface|null, status: OnesiBoxStatus|null, meeting_url: string|null, meeting_joined_at: CarbonInterface|null, audio_connected: bool|null}  $before
     */
    private function announceBackOnline(OnesiBox $onesiBox, array $before): void
    {
        $since = $before['last_seen_at'] !== null
            ? ' (era scollegato da '.$this->formatMinutes(max(1, (int) round($before['last_seen_at']->diffInMinutes(Date::now())))).')'
            : '';
        $lines = ["🟢 Di nuovo acceso e connesso{$since}."];

        if ($before['meeting_url'] !== null && $onesiBox->current_meeting_url === null) {
            $lines[] = 'Non è più nella riunione Zoom.';
        }

        $this->alerts->send($onesiBox, implode("\n", $lines));
    }

    /**
     * @param  array{connection_state: string|null, last_seen_at: CarbonInterface|null, status: OnesiBoxStatus|null, meeting_url: string|null, meeting_joined_at: CarbonInterface|null, audio_connected: bool|null}  $before
     */
    private function announceMeetingLeft(OnesiBox $onesiBox, array $before): void
    {
        $meetingUrl = (string) $before['meeting_url'];
        $joinedAt = $before['meeting_joined_at'];
        $after = $joinedAt !== null
            ? ' dopo '.$this->formatMinutes(max(1, (int) round($joinedAt->diffInMinutes(Date::now()))))
            : '';

        $askedToLeave = $onesiBox->commands()
            ->where('type', CommandType::LeaveZoom)
            ->when($joinedAt !== null, fn ($query) => $query->where('created_at', '>=', $joinedAt))
            ->exists();

        $this->alerts->send(
            $onesiBox,
            "📴 Uscito dalla riunione {$this->meetingName($onesiBox, $meetingUrl)}{$after}."
                .($askedToLeave ? '' : "\nLa riunione è stata chiusa dall'organizzatore o dal box, non da Onesiforo."),
        );
    }

    private function checkMeetingAudio(OnesiBox $onesiBox, ?bool $audioBefore): void
    {
        $audioNow = $onesiBox->current_meeting_audio_connected;

        if ($audioNow === false && $audioBefore !== false) {
            $this->alerts->send(
                $onesiBox,
                "🔇 È nella riunione ma Zoom non ha l'audio collegato: il box sta provando a ricollegarlo da solo.",
                kind: 'meeting-audio',
                quietSeconds: self::PROBLEM_QUIET_SECONDS,
            );
        }

        if ($audioNow === true && $audioBefore === false) {
            $this->alerts->send($onesiBox, "🔊 L'audio della riunione è di nuovo collegato.");
        }
    }

    private function isNearTheEnd(PlaybackEvent $playbackEvent): bool
    {
        return $playbackEvent->position !== null
            && $playbackEvent->duration !== null
            && $playbackEvent->duration > 0
            && $playbackEvent->position >= $playbackEvent->duration - self::END_TOLERANCE_SECONDS;
    }

    /**
     * Whether someone (or Onesiforo itself) asked the box to stop or show something else
     * after this media started, which makes the stop expected.
     */
    private function wasAskedToChange(OnesiBox $onesiBox, PlaybackEvent $playbackEvent): bool
    {
        $startedAt = $onesiBox->playbackEvents()
            ->where('event', PlaybackEventType::Started)
            ->where('media_url', $playbackEvent->media_url)
            ->where('id', '<', $playbackEvent->id)
            ->latest('id')
            ->value('created_at');

        return $onesiBox->commands()
            ->whereIn('type', [
                CommandType::StopMedia,
                CommandType::PlayMedia,
                CommandType::PlayStreamItem,
                CommandType::JoinZoom,
                CommandType::Reboot,
                CommandType::RestartService,
                CommandType::Shutdown,
            ])
            ->where('created_at', '>=', $startedAt ?? Date::now()->subMinutes(2))
            ->exists();
    }

    private function meetingName(OnesiBox $onesiBox, string $meetingUrl): string
    {
        $congregation = $onesiBox->recipient?->congregation;

        if ($congregation !== null && $congregation->zoom_url === $meetingUrl) {
            return "dell'adunanza ({$congregation->name})";
        }

        $savedName = ZoomMeeting::query()
            ->where('onesi_box_id', $onesiBox->id)
            ->where('zoom_url', $meetingUrl)
            ->value('name');

        return $savedName !== null ? "«{$savedName}»" : 'Zoom';
    }

    private function formatMinutes(int $minutes): string
    {
        if ($minutes < 60) {
            return $minutes === 1 ? '1 minuto' : "{$minutes} minuti";
        }

        $hours = intdiv($minutes, 60);
        $rest = $minutes % 60;

        return $rest === 0 ? "{$hours} h" : sprintf('%d h %02d min', $hours, $rest);
    }

    private function formatClock(int $seconds): string
    {
        return $seconds >= 3600 ? gmdate('G:i:s', $seconds) : gmdate('i:s', $seconds);
    }

    private function timezone(): string
    {
        return (string) config('app.display_timezone', 'Europe/Rome');
    }
}
