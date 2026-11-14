<?php

declare(strict_types=1);

use App\Enums\CommandType;
use App\Enums\OnesiBoxPermission;
use App\Enums\PlaybackSessionStatus;
use App\Livewire\Dashboard\Controls\JwMediaBrowser;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;

beforeEach(function (): void {
    $this->user = User::factory()->create();
    $this->box = OnesiBox::factory()->online()->withCaregiver($this->user)->create();
    $this->actingAs($this->user);

    fakeJwOrgCategories([
        'VideoOnDemand' => ['key' => 'VideoOnDemand', 'name' => 'Categorie', 'type' => 'container', 'subcategories' => [
            ['key' => 'VODStudio', 'name' => 'JW Broadcasting', 'type' => 'container', 'media' => []],
            ['key' => 'VODBible', 'name' => 'La Bibbia', 'type' => 'container', 'media' => []],
        ]],
        'VODStudio' => ['key' => 'VODStudio', 'name' => 'JW Broadcasting', 'type' => 'container', 'subcategories' => [
            ['key' => 'StudioTalks', 'name' => 'Discorsi', 'type' => 'ondemand', 'media' => [
                jwOrgMedia('pub-talk-1_I_VIDEO', 'Discorso sulla fede'),
                jwOrgMedia('pub-talk-2_I_VIDEO', 'Discorso sulla speranza'),
            ]],
        ]],
        'StudioTalks' => ['key' => 'StudioTalks', 'name' => 'Discorsi', 'type' => 'ondemand', 'media' => [
            jwOrgMedia('pub-talk-1_I_VIDEO', 'Discorso sulla fede'),
            jwOrgMedia('pub-talk-2_I_VIDEO', 'Discorso sulla speranza'),
        ]],
    ]);
});

it('expands a category and opens a subcategory with its videos', function (): void {
    Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video'])
        ->assertSee(['JW Broadcasting', 'La Bibbia'])
        ->assertDontSee('Discorsi')
        ->call('selectCategory', 'VODStudio')
        ->assertSee('Discorsi')
        ->call('openSubcategory', 'StudioTalks')
        ->assertSee(['Discorso sulla fede', 'Discorso sulla speranza']);
});

it('shows when each video was last shown on this box', function (): void {
    $this->travelTo(Date::parse('2026-11-14 10:00:00', 'UTC'));
    PlaybackEvent::factory()->started()->create([
        'onesi_box_id' => $this->box->id,
        'media_url' => 'https://www.jw.org/it/biblioteca-digitale/video/#it/mediaitems/StudioTalks/pub-talk-1_I_VIDEO',
        'created_at' => Date::parse('2026-11-13 17:05:00', 'UTC'),
    ]);

    Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video'])
        ->call('selectCategory', 'VODStudio')
        ->call('openSubcategory', 'StudioTalks')
        ->assertSeeInOrder(['Discorso sulla fede', 'Mostrato ieri alle 18:05', 'Discorso sulla speranza', 'Mai mostrato']);
});

it('filters the open category by title', function (): void {
    Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video'])
        ->call('selectCategory', 'VODStudio')
        ->call('openSubcategory', 'StudioTalks')
        ->set('search', 'speranza')
        ->assertSee('Discorso sulla speranza')
        ->assertDontSee('Discorso sulla fede');
});

it('sends the tapped video to the box', function (): void {
    Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video'])
        ->call('selectCategory', 'VODStudio')
        ->call('openSubcategory', 'StudioTalks')
        ->call('play', 'pub-talk-2_I_VIDEO');

    expect($this->box->commands()->where('type', CommandType::PlayMedia)->sole()->payload)->toMatchArray([
        'url' => 'https://www.jw.org/it/biblioteca-digitale/video/#it/mediaitems/TestCategory/pub-talk-2_I_VIDEO',
        'media_type' => 'video',
    ]);
});

it('does not send media that is not in the open category', function (): void {
    Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video'])
        ->call('selectCategory', 'VODStudio')
        ->call('openSubcategory', 'StudioTalks')
        ->call('play', 'pub-unknown_I_VIDEO');

    expect($this->box->commands()->count())->toBe(0);
});

it('ignores categories that are not part of the library', function (): void {
    Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video'])
        ->call('openSubcategory', 'StudioTalks')
        ->assertSet('openCategory', null);
});

it('forbids playing for a caregiver with read-only permission', function (): void {
    $viewer = User::factory()->create();
    $this->box->caregivers()->attach($viewer, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    Livewire::actingAs($viewer)
        ->test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video'])
        ->call('selectCategory', 'VODStudio')
        ->call('openSubcategory', 'StudioTalks')
        ->call('play', 'pub-talk-1_I_VIDEO')
        ->assertForbidden();

    expect($this->box->commands()->count())->toBe(0);
});

it('shows an error when jw.org cannot be reached', function (): void {
    Http::fake(['b.jw-cdn.org/apis/mediator/v1/categories/I/Audio?*' => Http::response(status: 500)]);

    Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'audio'])
        ->assertSee('Impossibile caricare i contenuti da jw.org');
});

/**
 * Open the "Discorsi" subcategory of the faked library.
 */
function openTalks(Testable $browser): Testable
{
    return $browser->call('selectCategory', 'VODStudio')->call('openSubcategory', 'StudioTalks');
}

it('queues videos without sending them and shows the playlist with its total duration', function (): void {
    openTalks(Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video']))
        ->assertDontSee('Avvia playlist')
        ->call('toggleQueued', 'pub-talk-2_I_VIDEO')
        ->call('toggleQueued', 'pub-talk-1_I_VIDEO')
        ->assertSet('queue.0.key', 'pub-talk-2_I_VIDEO')
        ->assertSet('queue.1.key', 'pub-talk-1_I_VIDEO')
        ->assertSee(['Playlist (2)', '10 min', 'Avvia playlist']);

    expect($this->box->commands()->count())->toBe(0);
});

it('takes a queued video out of the playlist when its button is tapped again', function (): void {
    openTalks(Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video']))
        ->call('toggleQueued', 'pub-talk-1_I_VIDEO')
        ->call('toggleQueued', 'pub-talk-2_I_VIDEO')
        ->call('toggleQueued', 'pub-talk-1_I_VIDEO')
        ->assertSet('queue', fn (array $queue): bool => array_column($queue, 'key') === ['pub-talk-2_I_VIDEO']);
});

it('keeps the playlist when the browser is opened again', function (): void {
    openTalks(Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video']))
        ->call('toggleQueued', 'pub-talk-1_I_VIDEO');

    Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video'])
        ->assertSee(['Playlist (1)', 'Discorso sulla fede']);

    Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'audio'])
        ->assertSet('queue', []);
});

it('does not queue media that is not in the open category', function (): void {
    openTalks(Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video']))
        ->call('toggleQueued', 'pub-unknown_I_VIDEO')
        ->assertSet('queue', []);
});

it('stops queueing at the playlist limit', function (): void {
    $fullQueue = array_map(
        fn (int $index): array => ['key' => "pub-queued-{$index}_VIDEO", 'title' => "Video {$index}", 'media_type' => 'video', 'duration_seconds' => 60, 'duration_formatted' => '1:00', 'thumbnail_url' => null, 'url' => "https://www.jw.org/it/biblioteca-digitale/video/#it/mediaitems/X/pub-queued-{$index}_VIDEO"],
        range(1, 100),
    );
    session()->put("jw-media-queue.{$this->box->id}.video", $fullQueue);

    openTalks(Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video']))
        ->call('toggleQueued', 'pub-talk-1_I_VIDEO')
        ->assertSet('queue', fn (array $queue): bool => count($queue) === 100);
});

it('plays the playlist in order and empties it', function (): void {
    openTalks(Livewire::test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video']))
        ->call('toggleQueued', 'pub-talk-2_I_VIDEO')
        ->call('toggleQueued', 'pub-talk-1_I_VIDEO')
        ->call('startQueue')
        ->assertSet('queue', [])
        ->assertDontSee('Avvia playlist');

    $session = $this->box->playbackSessions()->sole();

    expect($session->status)->toBe(PlaybackSessionStatus::Active)
        ->and($session->playlist->items()->orderBy('position')->pluck('title')->all())->toBe(['Discorso sulla speranza', 'Discorso sulla fede'])
        ->and($this->box->commands()->where('type', CommandType::PlayMedia)->sole()->payload['url'])->toEndWith('/pub-talk-2_I_VIDEO')
        ->and(session("jw-media-queue.{$this->box->id}.video"))->toBe([]);
});

it('forbids building or starting a playlist for a caregiver with read-only permission', function (string $action, array $arguments): void {
    $viewer = User::factory()->create();
    $this->box->caregivers()->attach($viewer, ['permission' => OnesiBoxPermission::ReadOnly->value]);

    openTalks(Livewire::actingAs($viewer)->test(JwMediaBrowser::class, ['onesiBox' => $this->box, 'mediaType' => 'video']))
        ->call($action, ...$arguments)
        ->assertForbidden();
})->with([
    'add to the playlist' => ['toggleQueued', ['pub-talk-1_I_VIDEO']],
    'start the playlist' => ['startQueue', []],
]);
