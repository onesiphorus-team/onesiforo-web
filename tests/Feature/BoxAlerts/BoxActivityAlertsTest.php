<?php

declare(strict_types=1);

use App\Enums\CommandType;
use App\Enums\OnesiBoxPermission;
use App\Enums\OnesiBoxStatus;
use App\Enums\PlaybackSessionStatus;
use App\Models\Command;
use App\Models\Congregation;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Recipient;
use App\Models\User;
use App\Notifications\BoxActivityNotification;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;

const SUPPORT_CHAT = '-1001234567890';

beforeEach(function (): void {
    Notification::fake();
    Http::preventStrayRequests();
    config([
        'services.telegram-bot-api.support_chat_id' => SUPPORT_CHAT,
        'services.telegram-bot-api.support_topic_id' => '42',
    ]);

    $this->congregation = Congregation::factory()->create(['name' => 'Cappelle sul Tavo']);
    $this->box = OnesiBox::factory()->online()->create([
        'name' => 'OnesiBox Soggiorno',
        'connection_state' => 'online',
        'recipient_id' => Recipient::factory()->create(['first_name' => 'Teresa', 'last_name' => 'Bianchi', 'congregation_id' => $this->congregation->id])->id,
    ]);
    $this->token = $this->box->createToken('onesibox-api-token')->plainTextToken;
});

/**
 * @return list<string>
 */
function sentAlerts(): array
{
    $alerts = [];

    Notification::assertSentOnDemand(BoxActivityNotification::class, function (BoxActivityNotification $notification) use (&$alerts): bool {
        $alerts[] = $notification->html;

        return true;
    });

    return $alerts;
}

function heartbeat(array $payload): void
{
    test()->postJson(route('api.v1.appliances.heartbeat'), $payload, ['Authorization' => 'Bearer '.test()->token])->assertOk();
}

function playbackEvent(array $payload): void
{
    test()->postJson(route('api.v1.appliances.playback'), ['media_type' => 'video', ...$payload], ['Authorization' => 'Bearer '.test()->token])->assertOk();
}

describe('box turned on and off', function (): void {
    it('announces once a box that stopped answering, saying it was in a meeting', function (): void {
        $this->box->update([
            'last_seen_at' => now()->subMinutes(6),
            'status' => OnesiBoxStatus::Calling,
            'current_meeting_id' => '123',
            'current_meeting_url' => $this->congregation->zoom_url,
        ]);

        $this->artisan('onesibox:check-connections')->assertSuccessful();
        $this->artisan('onesibox:check-connections')->assertSuccessful();

        expect(sentAlerts())->toHaveCount(1)
            ->and(sentAlerts()[0])->toContain('<b>Teresa Bianchi</b> · OnesiBox Soggiorno', 'Spento o non connesso', 'Era collegato alla riunione dell&#039;adunanza (Cappelle sul Tavo)')
            ->and($this->box->refresh()->connection_state)->toBe('offline');
    });

    it('announces the box coming back, and how long it was away', function (): void {
        $this->box->update(['connection_state' => 'offline', 'last_seen_at' => now()->subMinutes(40)]);

        heartbeat(['status' => OnesiBoxStatus::Idle->value]);

        expect(sentAlerts())->toHaveCount(1)
            ->and(sentAlerts()[0])->toContain('Di nuovo acceso e connesso (era scollegato da 40 minuti)')
            ->and($this->box->refresh()->connection_state)->toBe('online');
    });

    it('stays quiet on the first heartbeat after the update, when the state is still unknown', function (): void {
        $this->box->update(['connection_state' => null]);

        heartbeat(['status' => OnesiBoxStatus::Idle->value]);

        Notification::assertNothingSent();
        expect($this->box->refresh()->connection_state)->toBe('online');
    });
});

describe('meetings', function (): void {
    it('announces entering and leaving a meeting, and who closed it', function (): void {
        heartbeat(['status' => OnesiBoxStatus::Calling->value, 'current_meeting' => ['meeting_id' => '123', 'meeting_url' => $this->congregation->zoom_url, 'joined_at' => now()->subHour()->toIso8601String()]]);
        $this->travel(90)->minutes();
        heartbeat(['status' => OnesiBoxStatus::Idle->value]);

        expect(sentAlerts())->toHaveCount(2)
            ->and(sentAlerts()[0])->toContain('Entrato nella riunione dell&#039;adunanza (Cappelle sul Tavo)')
            ->and(sentAlerts()[1])->toContain('Uscito dalla riunione', 'chiusa dall&#039;organizzatore o dal box');
    });

    it('does not blame anyone when Onesiforo asked the box to leave', function (): void {
        heartbeat(['status' => OnesiBoxStatus::Calling->value, 'current_meeting' => ['meeting_id' => '123', 'meeting_url' => 'https://us05web.zoom.us/j/123', 'joined_at' => now()->toIso8601String()]]);
        Command::factory()->for($this->box, 'onesiBox')->create(['type' => CommandType::LeaveZoom]);
        heartbeat(['status' => OnesiBoxStatus::Idle->value]);

        expect(sentAlerts()[1])->toContain('Uscito dalla riunione Zoom')->not->toContain('organizzatore');
    });

    it('warns once when Zoom stays without audio, and says when it is back', function (): void {
        $meeting = ['meeting_id' => '123', 'meeting_url' => 'https://us05web.zoom.us/j/123', 'joined_at' => now()->toIso8601String()];

        heartbeat(['status' => OnesiBoxStatus::Calling->value, 'current_meeting' => [...$meeting, 'audio_connected' => true]]);
        heartbeat(['status' => OnesiBoxStatus::Calling->value, 'current_meeting' => [...$meeting, 'audio_connected' => false]]);
        heartbeat(['status' => OnesiBoxStatus::Calling->value, 'current_meeting' => [...$meeting, 'audio_connected' => false]]);
        heartbeat(['status' => OnesiBoxStatus::Calling->value, 'current_meeting' => [...$meeting, 'audio_connected' => true]]);

        expect(sentAlerts())->toHaveCount(3)
            ->and(sentAlerts()[1])->toContain('Zoom non ha l&#039;audio collegato')
            ->and(sentAlerts()[2])->toContain('di nuovo collegato');
    });

    it('does not warn about audio when the box does not report it', function (): void {
        heartbeat(['status' => OnesiBoxStatus::Calling->value, 'current_meeting' => ['meeting_id' => '123', 'meeting_url' => 'https://us05web.zoom.us/j/123', 'joined_at' => now()->toIso8601String()]]);
        heartbeat(['status' => OnesiBoxStatus::Calling->value, 'current_meeting' => ['meeting_id' => '123', 'meeting_url' => 'https://us05web.zoom.us/j/123', 'joined_at' => now()->toIso8601String()]]);

        expect(sentAlerts())->toHaveCount(1);
    });
});

describe('single contents', function (): void {
    it('announces the start with the title, once for contents started in a row', function (): void {
        Http::fake(['b.jw-cdn.org/apis/mediator/v1/media-items/I/pub-ebtv_103_VIDEO?*' => Http::response(['media' => [jwOrgMedia('pub-ebtv_103_VIDEO', 'Si può essere amici di Dio?')]])]);

        playbackEvent(['event' => 'started', 'media_url' => 'https://www.jw.org/it/biblioteca-digitale/video/#it/mediaitems/X/pub-ebtv_103_VIDEO']);
        playbackEvent(['event' => 'started', 'media_url' => 'https://www.jw.org/it/biblioteca-digitale/video/#it/mediaitems/X/pub-other_VIDEO']);

        expect(sentAlerts())->toHaveCount(1)
            ->and(sentAlerts()[0])->toContain('Sta mostrando «Si può essere amici di Dio?»');
    });

    it('announces the end of a content', function (): void {
        playbackEvent(['event' => 'completed', 'media_url' => 'https://www.jw.org/video/a']);

        expect(sentAlerts()[0])->toContain('Finito');
    });

    it('warns when playback stops before the end without anyone asking', function (): void {
        playbackEvent(['event' => 'started', 'media_url' => 'https://www.jw.org/video/a']);
        $this->travel(5)->minutes();
        playbackEvent(['event' => 'stopped', 'media_url' => 'https://www.jw.org/video/a', 'position' => 192, 'duration' => 760]);

        expect(sentAlerts()[1])->toContain('interrotta prima della fine (a 03:12 di 12:40)');
    });

    it('stays quiet when a caregiver stopped it or started something else', function (): void {
        playbackEvent(['event' => 'started', 'media_url' => 'https://www.jw.org/video/a']);
        Command::factory()->for($this->box, 'onesiBox')->create(['type' => CommandType::StopMedia]);
        playbackEvent(['event' => 'stopped', 'media_url' => 'https://www.jw.org/video/a', 'position' => 100, 'duration' => 760]);

        expect(sentAlerts())->toHaveCount(1);
    });

    it('treats a stop right at the end as the end', function (): void {
        playbackEvent(['event' => 'started', 'media_url' => 'https://www.jw.org/video/a']);
        $this->travel(3)->minutes();
        playbackEvent(['event' => 'stopped', 'media_url' => 'https://www.jw.org/video/a', 'position' => 755, 'duration' => 760]);

        expect(sentAlerts()[1])->toContain('Finito');
    });

    it('reports playback errors at most once in ten minutes', function (): void {
        playbackEvent(['event' => 'error', 'media_url' => 'https://www.jw.org/video/a', 'error_message' => 'Nessun media', 'error_code' => 'E110']);
        playbackEvent(['event' => 'error', 'media_url' => 'https://www.jw.org/video/b', 'error_message' => 'Nessun media', 'error_code' => 'E110']);

        expect(sentAlerts())->toHaveCount(1)
            ->and(sentAlerts()[0])->toContain('interrotta per un errore', 'Errore: Nessun media');
    });
});

describe('playlists', function (): void {
    it('announces a playlist once when it starts and once when it ends, not for each item', function (): void {
        $playlist = Playlist::factory()->forOnesiBox($this->box)->create(['name' => 'Playlist di video']);
        foreach (['https://www.jw.org/video/1', 'https://www.jw.org/video/2'] as $position => $url) {
            PlaylistItem::factory()->for($playlist)->atPosition($position)->create(['media_url' => $url, 'duration_seconds' => 600]);
        }
        $session = PlaybackSession::factory()->forOnesiBox($this->box)->forPlaylist($playlist)->create();

        playbackEvent(['event' => 'started', 'media_url' => 'https://www.jw.org/video/1', 'session_id' => $session->uuid]);
        playbackEvent(['event' => 'completed', 'media_url' => 'https://www.jw.org/video/1', 'session_id' => $session->uuid]);
        playbackEvent(['event' => 'started', 'media_url' => 'https://www.jw.org/video/2', 'session_id' => $session->uuid]);
        playbackEvent(['event' => 'completed', 'media_url' => 'https://www.jw.org/video/2', 'session_id' => $session->uuid]);

        expect(sentAlerts())->toHaveCount(2)
            ->and(sentAlerts()[0])->toContain('Playlist di video avviata: 2 contenuti · circa 20 minuti')
            ->and(sentAlerts()[1])->toContain('Playlist di video finita: 2 di 2 contenuti mostrati')
            ->and($session->refresh()->status)->toBe(PlaybackSessionStatus::Completed);
    });
});

describe('who gets the alerts', function (): void {
    it('posts in the topic of the support chat', function (): void {
        playbackEvent(['event' => 'completed', 'media_url' => 'https://www.jw.org/video/a']);

        Notification::assertSentOnDemand(
            BoxActivityNotification::class,
            fn (BoxActivityNotification $notification, array $channels, AnonymousNotifiable $notifiable): bool => $notifiable->routes['telegram'] === SUPPORT_CHAT
                && $notification->topicId === 42
                && $notification->toTelegram($notifiable)->getPayloadValue('message_thread_id') === 42,
        );
    });

    it('without a support chat, writes once to each chat of the caregivers of the box', function (): void {
        config(['services.telegram-bot-api.support_chat_id' => null]);
        foreach (['-100555', '-100555', '777', null] as $chatId) {
            $this->box->caregivers()->attach(User::factory()->create(['telegram_chat_id' => $chatId]), ['permission' => OnesiBoxPermission::Full->value]);
        }

        playbackEvent(['event' => 'completed', 'media_url' => 'https://www.jw.org/video/a']);

        $chats = [];
        Notification::assertSentOnDemand(BoxActivityNotification::class, function ($notification, $channels, AnonymousNotifiable $notifiable) use (&$chats): bool {
            $chats[] = $notifiable->routes['telegram'];

            return $notification->topicId === null;
        });
        expect($chats)->toEqualCanonicalizing(['-100555', '777']);
    });

    it('sends nothing for a box with the alerts turned off', function (): void {
        $this->box->update(['activity_notifications_enabled' => false, 'last_seen_at' => now()->subMinutes(10)]);

        $this->artisan('onesibox:check-connections')->assertSuccessful();
        playbackEvent(['event' => 'completed', 'media_url' => 'https://www.jw.org/video/a']);

        Notification::assertNothingSent();
    });
});
