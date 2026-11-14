<?php

declare(strict_types=1);

use App\Actions\Sessions\StartRandomPlaybackSessionAction;
use App\Enums\CommandType;
use App\Enums\PlaybackSessionStatus;
use App\Enums\PlaylistSourceType;
use App\Exceptions\NoRandomMediaAvailableException;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;

beforeEach(function (): void {
    $this->onesiBox = OnesiBox::factory()->online()->create();
    $this->startRandomSession = resolve(StartRandomPlaybackSessionAction::class);
});

/**
 * @param  list<array<string, mixed>>  $media
 */
function fakeVideoCategory(array $media): void
{
    fakeJwOrgCategories([
        'VODFamily' => ['key' => 'VODFamily', 'name' => 'Famiglia', 'type' => 'container', 'subcategories' => [
            ['key' => 'FamilyWorship', 'name' => 'Culto', 'type' => 'ondemand', 'media' => $media],
        ]],
    ]);
}

function markShown(OnesiBox $onesiBox, string $naturalKey, string $when): void
{
    PlaybackEvent::factory()->started()->create([
        'onesi_box_id' => $onesiBox->id,
        'media_url' => "https://www.jw.org/it/biblioteca-digitale/video/#it/mediaitems/FamilyWorship/{$naturalKey}",
        'created_at' => now()->modify($when),
    ]);
}

it('puts never shown media first, then the ones shown least recently', function (): void {
    fakeVideoCategory([
        jwOrgMedia('pub-yesterday_VIDEO', 'Visto ieri', 600),
        jwOrgMedia('pub-never-1_VIDEO', 'Mai visto 1', 600),
        jwOrgMedia('pub-last-month_VIDEO', 'Visto il mese scorso', 600),
        jwOrgMedia('pub-never-2_VIDEO', 'Mai visto 2', 600),
    ]);
    markShown($this->onesiBox, 'pub-yesterday_VIDEO', '-1 day');
    markShown($this->onesiBox, 'pub-last-month_VIDEO', '-1 month');

    $session = $this->startRandomSession->execute($this->onesiBox, 'video', ['VODFamily'], 60);

    $titles = $session->playlist->items()->orderBy('position')->pluck('title')->all();

    expect(array_slice($titles, 0, 2))->toEqualCanonicalizing(['Mai visto 1', 'Mai visto 2'])
        ->and(array_slice($titles, 2))->toBe(['Visto il mese scorso', 'Visto ieri']);
});

it('stops adding media once the session duration is filled', function (): void {
    fakeVideoCategory([
        jwOrgMedia('pub-1_VIDEO', 'Uno', 20 * 60),
        jwOrgMedia('pub-2_VIDEO', 'Due', 20 * 60),
        jwOrgMedia('pub-3_VIDEO', 'Tre', 20 * 60),
    ]);

    $session = $this->startRandomSession->execute($this->onesiBox, 'video', ['VODFamily'], 30);

    expect($session->playlist->items)->toHaveCount(2);
});

it('skips media longer than 30 minutes unless long media are included', function (bool $includeLongItems, int $expectedCount): void {
    fakeVideoCategory([
        jwOrgMedia('pub-short_VIDEO', 'Breve', 10 * 60),
        jwOrgMedia('pub-long_VIDEO', 'Programma mensile', 45 * 60),
    ]);

    $session = $this->startRandomSession->execute($this->onesiBox, 'video', ['VODFamily'], 120, $includeLongItems);

    expect($session->playlist->items)->toHaveCount($expectedCount);
})->with([
    'long media excluded' => [false, 1],
    'long media included' => [true, 2],
]);

it('starts an audio session that sends audio play commands', function (): void {
    fakeJwOrgCategories([
        'AudioOriginalSongs' => ['key' => 'AudioOriginalSongs', 'name' => 'Canzoni', 'type' => 'ondemand', 'media' => [
            jwOrgMedia('pub-osg_I_1_AUDIO', 'Canzone', 240, 'audio'),
        ]],
    ]);

    $session = $this->startRandomSession->execute($this->onesiBox, 'audio', ['AudioOriginalSongs'], 30);

    $command = $this->onesiBox->commands()->where('type', CommandType::PlayMedia)->sole();

    expect($session)
        ->status->toBe(PlaybackSessionStatus::Active)
        ->media_type->toBe('audio')
        ->and($session->playlist)
        ->source_type->toBe(PlaylistSourceType::JworgRandom)
        ->name->toBe('Audio casuali')
        ->and($command->payload)
        ->toMatchArray([
            'url' => 'https://www.jw.org/it/biblioteca-digitale/audio/#it/mediaitems/TestCategory/pub-osg_I_1_AUDIO',
            'media_type' => 'audio',
            'session_id' => $session->uuid,
        ]);
});

it('throws when no media fits the session', function (): void {
    fakeVideoCategory([jwOrgMedia('pub-long_VIDEO', 'Programma mensile', 45 * 60)]);

    $this->startRandomSession->execute($this->onesiBox, 'video', ['VODFamily'], 30);
})->throws(NoRandomMediaAvailableException::class);
