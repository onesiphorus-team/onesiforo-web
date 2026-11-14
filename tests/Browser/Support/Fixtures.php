<?php

declare(strict_types=1);

namespace Tests\Browser\Support;

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\OnesiBoxPermission;
use App\Enums\OnesiBoxStatus;
use App\Models\ApplianceScreenshot;
use App\Models\Command;
use App\Models\Congregation;
use App\Models\CustomCommand;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
use App\Models\PlaybackSession;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

/**
 * Realistic data for the browser tests: boxes in every state and a fake JW.org Mediator API.
 */
final class Fixtures
{
    public const VIDEO_URL = 'https://www.jw.org/it/biblioteca-digitale/video/#it/mediaitems/StudioMonthlyPrograms/pub-jwb-112026_1_VIDEO';

    public const AUDIO_URL = 'https://www.jw.org/it/biblioteca-digitale/audio/#it/mediaitems/AudioOriginalSongs/pub-osg_I_119_AUDIO';

    /**
     * Create a caregiver and a box for an elderly recipient, and sign the caregiver in.
     *
     * @param  array<string, mixed>  $attributes  Extra OnesiBox attributes
     * @return array{0: User, 1: OnesiBox}
     */
    public static function boxFor(
        string $firstName = 'Maria',
        string $lastName = 'Rossi',
        string $boxName = 'Box Salotto',
        OnesiBoxPermission $permission = OnesiBoxPermission::Full,
        bool $online = true,
        array $attributes = [],
        ?User $user = null,
    ): array {
        $user ??= User::factory()->role(\App\Enums\Roles::Caregiver)->create(['name' => 'Giulia Rossi', 'email' => 'giulia.rossi@example.com']);

        $recipient = Recipient::factory()->withEmergencyContacts()->create([
            'first_name' => $firstName,
            'last_name' => $lastName,
            'phone' => '+39 02 5551234',
            'street' => 'Via Giuseppe Verdi 12',
            'city' => 'Milano',
            'province' => 'MI',
            'postal_code' => '20121',
            'emergency_contacts' => [['name' => 'Paolo Rossi', 'phone' => '+39 333 1234567', 'relationship' => 'Figlio']],
            'congregation_id' => Congregation::factory()->create([
                'name' => 'Milano Loreto',
                'zoom_url' => 'https://us05web.zoom.us/j/81234567890?pwd=abc123XYZ',
            ])->id,
        ]);

        $factory = OnesiBox::factory()->forRecipient($recipient);
        $factory = $online ? $factory->online() : $factory->offline();

        $box = $factory->create(['name' => $boxName, 'app_version' => '0.13.0', ...$attributes]);
        $box->caregivers()->attach($user, ['permission' => $permission->value]);

        test()->actingAs($user);

        return [$user, $box];
    }

    public static function playVideo(OnesiBox $box, bool $paused = false): void
    {
        $box->update([
            'status' => OnesiBoxStatus::Playing,
            'current_media_url' => self::VIDEO_URL,
            'current_media_type' => 'video',
            'current_media_title' => 'Programma mensile di novembre 2026',
            'current_media_position' => 754,
            'current_media_duration' => 1620,
        ]);

        self::event($box, $paused ? 'paused' : 'started', self::VIDEO_URL, 'video');
    }

    public static function playAudio(OnesiBox $box): void
    {
        $box->update([
            'status' => OnesiBoxStatus::Playing,
            'current_media_url' => self::AUDIO_URL,
            'current_media_type' => 'audio',
            'current_media_title' => 'In Geova io confiderò',
            'current_media_position' => 92,
            'current_media_duration' => 264,
        ]);

        self::event($box, 'started', self::AUDIO_URL, 'audio');
    }

    public static function inZoomCall(OnesiBox $box): void
    {
        $box->update([
            'status' => OnesiBoxStatus::Calling,
            'current_meeting_id' => '81234567890',
            'current_meeting_url' => 'https://us05web.zoom.us/j/81234567890?pwd=abc123XYZ',
            'current_meeting_joined_at' => now()->subMinutes(23),
        ]);
    }

    /**
     * A running playlist session with the first item playing and more to come.
     */
    public static function startSession(OnesiBox $box): PlaybackSession
    {
        self::playVideo($box);

        $playlist = Playlist::factory()->forOnesiBox($box)->create(['name' => 'Video casuali']);

        foreach ([
            ['Programma mensile di novembre 2026', 1620, 'StudioMonthlyPrograms/pub-jwb-112026_1_VIDEO'],
            ['Il valore della preghiera', 540, 'StudioTalks/pub-jwb-talk-2_I_VIDEO'],
            ['Una famiglia unita nella fede', 780, 'StudioTalks/pub-jwb-talk-3_I_VIDEO'],
            ['Il Regno di Dio è vicino', 912, 'StudioTalks/pub-jwb-talk-4_I_VIDEO'],
            ['La speranza per i nostri cari', 655, 'StudioTalks/pub-jwb-talk-5_I_VIDEO'],
        ] as $position => [$title, $seconds, $path]) {
            PlaylistItem::factory()->create([
                'playlist_id' => $playlist->id,
                'media_url' => 'https://www.jw.org/it/biblioteca-digitale/video/#it/mediaitems/'.$path,
                'title' => $title,
                'duration_seconds' => $seconds,
                'position' => $position,
            ]);
        }

        return PlaybackSession::factory()->forOnesiBox($box)->create([
            'playlist_id' => $playlist->id,
            'duration_minutes' => 60,
            'started_at' => now()->subMinutes(12),
            'current_position' => 0,
        ]);
    }

    public static function pendingCommand(OnesiBox $box): Command
    {
        return Command::factory()->create([
            'onesi_box_id' => $box->id,
            'type' => CommandType::SetVolume,
            'payload' => ['level' => 60],
            'status' => CommandStatus::Pending,
            'expires_at' => now()->addMinutes(5),
        ]);
    }

    /**
     * @return list<CustomCommand>
     */
    public static function customCommands(OnesiBox $box): array
    {
        return [
            CustomCommand::factory()->forBox($box)->create(['name' => 'Accendi la TV', 'description' => 'Accende il televisore e sceglie l\'ingresso giusto', 'script_name' => 'tv-on.sh', 'icon' => 'heroicon-o-tv', 'sort_order' => 1]),
            CustomCommand::factory()->forBox($box)->create(['name' => 'Spegni la TV', 'description' => null, 'script_name' => 'tv-off.sh', 'icon' => 'heroicon-o-power', 'sort_order' => 2]),
        ];
    }

    /**
     * Store a believable picture of the TV screen as the latest box screenshot.
     */
    public static function screenshot(OnesiBox $box): ApplianceScreenshot
    {
        Storage::fake('local');

        $image = imagecreatetruecolor(1280, 720);
        imagefilledrectangle($image, 0, 0, 1280, 720, imagecolorallocate($image, 24, 38, 64));
        imagefilledrectangle($image, 0, 560, 1280, 720, imagecolorallocate($image, 12, 20, 36));
        imagefilledellipse($image, 640, 300, 220, 220, imagecolorallocate($image, 238, 190, 70));
        imagestring($image, 5, 520, 440, 'PROGRAMMA MENSILE - NOVEMBRE 2026', imagecolorallocate($image, 255, 255, 255));
        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        $path = "screenshots/{$box->id}/latest.png";
        Storage::disk('local')->put($path, $png);

        return ApplianceScreenshot::query()->create([
            'onesi_box_id' => $box->id,
            'captured_at' => now()->subSeconds(40),
            'width' => 1280,
            'height' => 720,
            'bytes' => strlen($png),
            'storage_path' => $path,
        ]);
    }

    /**
     * Fake the JW.org Mediator API (library categories and single media items) with Italian titles.
     */
    public static function fakeJwLibrary(): void
    {
        $video = fn (string $key, string $title, int $seconds): array => self::media($key, $title, $seconds, 'video');
        $audio = fn (string $key, string $title, int $seconds): array => self::media($key, $title, $seconds, 'audio');

        $categories = [
            'VideoOnDemand' => ['key' => 'VideoOnDemand', 'name' => 'Video', 'type' => 'container', 'subcategories' => [
                ['key' => 'VODStudio', 'name' => 'JW Broadcasting', 'type' => 'container', 'media' => []],
                ['key' => 'VODBible', 'name' => 'La Bibbia', 'type' => 'container', 'media' => []],
                ['key' => 'VODFamily', 'name' => 'Famiglia', 'type' => 'ondemand', 'media' => [
                    $video('pub-jwb-fam-1_I_VIDEO', 'Come comunicare in famiglia', 420),
                    $video('pub-jwb-fam-2_I_VIDEO', 'Aiutare chi è anziano', 515),
                ]],
            ]],
            'VODStudio' => ['key' => 'VODStudio', 'name' => 'JW Broadcasting', 'type' => 'container', 'subcategories' => [
                ['key' => 'StudioMonthlyPrograms', 'name' => 'Programmi mensili', 'type' => 'ondemand', 'media' => [
                    $video('pub-jwb-112026_1_VIDEO', 'Programma mensile di novembre 2026', 1620),
                    $video('pub-jwb-102026_1_VIDEO', 'Programma mensile di ottobre 2026', 1584),
                    $video('pub-jwb-092026_1_VIDEO', 'Programma mensile di settembre 2026', 1650),
                ]],
                ['key' => 'StudioTalks', 'name' => 'Discorsi', 'type' => 'ondemand', 'media' => [
                    $video('pub-jwb-talk-2_I_VIDEO', 'Il valore della preghiera', 540),
                    $video('pub-jwb-talk-3_I_VIDEO', 'Una famiglia unita nella fede', 780),
                    $video('pub-jwb-talk-4_I_VIDEO', 'Il Regno di Dio è vicino', 912),
                    $video('pub-jwb-talk-5_I_VIDEO', 'La speranza per i nostri cari', 655),
                    $video('pub-jwb-talk-6_I_VIDEO', 'Una lunga adunanza speciale (oltre mezz\'ora)', 2400),
                ]],
            ]],
            'VODBible' => ['key' => 'VODBible', 'name' => 'La Bibbia', 'type' => 'container', 'subcategories' => [
                ['key' => 'VODBibleStories', 'name' => 'Racconti biblici', 'type' => 'ondemand', 'media' => [
                    $video('pub-bible-1_I_VIDEO', 'Davide e Golia', 610),
                ]],
            ]],
            'StudioMonthlyPrograms' => ['key' => 'StudioMonthlyPrograms', 'name' => 'Programmi mensili', 'type' => 'ondemand', 'media' => [
                $video('pub-jwb-112026_1_VIDEO', 'Programma mensile di novembre 2026', 1620),
                $video('pub-jwb-102026_1_VIDEO', 'Programma mensile di ottobre 2026', 1584),
                $video('pub-jwb-092026_1_VIDEO', 'Programma mensile di settembre 2026', 1650),
            ]],
            'StudioTalks' => ['key' => 'StudioTalks', 'name' => 'Discorsi', 'type' => 'ondemand', 'media' => [
                $video('pub-jwb-talk-2_I_VIDEO', 'Il valore della preghiera', 540),
                $video('pub-jwb-talk-3_I_VIDEO', 'Una famiglia unita nella fede', 780),
                $video('pub-jwb-talk-4_I_VIDEO', 'Il Regno di Dio è vicino', 912),
                $video('pub-jwb-talk-5_I_VIDEO', 'La speranza per i nostri cari', 655),
                $video('pub-jwb-talk-6_I_VIDEO', 'Una lunga adunanza speciale (oltre mezz\'ora)', 2400),
            ]],
            'VODFamily' => ['key' => 'VODFamily', 'name' => 'Famiglia', 'type' => 'ondemand', 'media' => [
                $video('pub-jwb-fam-1_I_VIDEO', 'Come comunicare in famiglia', 420),
                $video('pub-jwb-fam-2_I_VIDEO', 'Aiutare chi è anziano', 515),
            ]],
            'VODBibleStories' => ['key' => 'VODBibleStories', 'name' => 'Racconti biblici', 'type' => 'ondemand', 'media' => [
                $video('pub-bible-1_I_VIDEO', 'Davide e Golia', 610),
            ]],
            'Audio' => ['key' => 'Audio', 'name' => 'Audio', 'type' => 'container', 'subcategories' => [
                ['key' => 'AudioOriginalSongs', 'name' => 'Cantici originali', 'type' => 'ondemand', 'media' => [
                    $audio('pub-osg_I_119_AUDIO', 'In Geova io confiderò', 264),
                    $audio('pub-osg_I_8_AUDIO', 'Geova è il nostro rifugio', 241),
                    $audio('pub-osg_I_54_AUDIO', 'Camminiamo per fede', 233),
                ]],
                ['key' => 'AudioBibleReadings', 'name' => 'Lettura della Bibbia', 'type' => 'ondemand', 'media' => [
                    $audio('nwt_I_19_Ps_001_AUDIO', 'Salmo 1', 95),
                    $audio('nwt_I_19_Ps_023_AUDIO', 'Salmo 23', 110),
                ]],
            ]],
            'AudioOriginalSongs' => ['key' => 'AudioOriginalSongs', 'name' => 'Cantici originali', 'type' => 'ondemand', 'media' => [
                $audio('pub-osg_I_119_AUDIO', 'In Geova io confiderò', 264),
                $audio('pub-osg_I_8_AUDIO', 'Geova è il nostro rifugio', 241),
                $audio('pub-osg_I_54_AUDIO', 'Camminiamo per fede', 233),
            ]],
            'AudioBibleReadings' => ['key' => 'AudioBibleReadings', 'name' => 'Lettura della Bibbia', 'type' => 'ondemand', 'media' => [
                $audio('nwt_I_19_Ps_001_AUDIO', 'Salmo 1', 95),
                $audio('nwt_I_19_Ps_023_AUDIO', 'Salmo 23', 110),
            ]],
        ];

        $items = [];
        foreach ($categories as $category) {
            foreach ([...($category['media'] ?? []), ...array_merge(...array_map(fn (array $sub): array => $sub['media'] ?? [], $category['subcategories'] ?? []))] as $media) {
                $items[$media['languageAgnosticNaturalKey']] = $media;
            }
        }

        Http::preventStrayRequests();
        Http::fake(function (Request $request) use ($categories, $items) {
            $path = parse_url($request->url(), PHP_URL_PATH) ?? '';

            if (preg_match('#/mediator/v1/categories/I/([A-Za-z]+)$#', $path, $m) === 1 && isset($categories[$m[1]])) {
                return Http::response(['category' => $categories[$m[1]]]);
            }

            if (preg_match('#/mediator/v1/media-items/I/([A-Za-z0-9_-]+)$#', $path, $m) === 1 && isset($items[$m[1]])) {
                return Http::response(['media' => [$items[$m[1]]]]);
            }

            return Http::response(status: 404);
        });
    }

    /**
     * @return array<string, mixed>
     */
    private static function media(string $key, string $title, int $seconds, string $type): array
    {
        return [
            'naturalKey' => "italian-{$key}",
            'languageAgnosticNaturalKey' => $key,
            'title' => $title,
            'type' => $type,
            'duration' => $seconds,
            'primaryCategory' => 'Any',
            // Thumbnails are left out: the browser tests have no network, so cards show their placeholder icon
        ];
    }

    private static function event(OnesiBox $box, string $event, string $url, string $type): void
    {
        $factory = PlaybackEvent::factory();
        $factory = $event === 'paused' ? $factory->paused() : $factory->started();

        $factory->create([
            'onesi_box_id' => $box->id,
            'media_url' => $url,
            'media_type' => $type,
            'created_at' => now()->subMinutes(5),
        ]);
    }
}
