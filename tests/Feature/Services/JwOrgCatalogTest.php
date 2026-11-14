<?php

declare(strict_types=1);

use App\Services\JwOrgCatalog;
use Illuminate\Support\Facades\Http;

it('normalizes a category with its subcategories and media', function (): void {
    fakeJwOrgCategories([
        'VODStudio' => [
            'key' => 'VODStudio',
            'name' => 'JW Broadcasting',
            'type' => 'container',
            'subcategories' => [
                [
                    'key' => 'StudioTalks',
                    'name' => 'Discorsi',
                    'type' => 'ondemand',
                    'media' => [jwOrgMedia('pub-jwb-1_I_1_VIDEO', 'Discorso uno', 754.6)],
                ],
            ],
        ],
    ]);

    $category = resolve(JwOrgCatalog::class)->category('VODStudio');

    expect($category)
        ->key->toBe('VODStudio')
        ->name->toBe('JW Broadcasting')
        ->is_container->toBeTrue()
        ->and($category['subcategories'][0])
        ->key->toBe('StudioTalks')
        ->is_container->toBeFalse()
        ->and($category['subcategories'][0]['media'][0])->toBe([
            'key' => 'pub-jwb-1_I_1_VIDEO',
            'title' => 'Discorso uno',
            'media_type' => 'video',
            'duration_seconds' => 755,
            'duration_formatted' => '12:35',
            'thumbnail_url' => 'https://cms-imgp.jw-cdn.org/img/pub-jwb-1_I_1_VIDEO_wss_sm.jpg',
            'url' => 'https://www.jw.org/it/biblioteca-digitale/video/#it/mediaitems/TestCategory/pub-jwb-1_I_1_VIDEO',
        ]);
});

it('falls back to the natural key when the language-agnostic key is missing', function (): void {
    fakeJwOrgCategories([
        'StudioTalks' => ['key' => 'StudioTalks', 'name' => 'Discorsi', 'type' => 'ondemand', 'media' => [
            ['naturalKey' => 'pub-jwb-1_I_1_VIDEO', 'title' => 'Discorso', 'duration' => 60],
        ]],
    ]);

    $talk = resolve(JwOrgCatalog::class)->category('StudioTalks')['media'][0];

    expect($talk)
        ->key->toBe('pub-jwb-1_I_1_VIDEO')
        ->url->toEndWith('/mediaitems/StudioTalks/pub-jwb-1_I_1_VIDEO');
});

it('builds audio URLs for audio media', function (): void {
    fakeJwOrgCategories([
        'Audio' => [
            'key' => 'Audio',
            'name' => 'Audio',
            'type' => 'container',
            'subcategories' => [
                ['key' => 'AudioOriginalSongs', 'name' => 'Canzoni', 'type' => 'ondemand', 'media' => [
                    jwOrgMedia('pub-osg_I_119_AUDIO', 'In Geova io confiderò', 264.4, 'audio'),
                ]],
            ],
        ],
    ]);

    $song = resolve(JwOrgCatalog::class)->rootCategory('audio')['subcategories'][0]['media'][0];

    expect($song)
        ->media_type->toBe('audio')
        ->url->toBe('https://www.jw.org/it/biblioteca-digitale/audio/#it/mediaitems/TestCategory/pub-osg_I_119_AUDIO');
});

it('collects the media of several categories without duplicates', function (): void {
    $sharedVideo = jwOrgMedia('pub-shared_I_VIDEO', 'Video in due categorie');

    fakeJwOrgCategories([
        'VODBible' => ['key' => 'VODBible', 'name' => 'La Bibbia', 'type' => 'container', 'subcategories' => [
            ['key' => 'BibleBooks', 'name' => 'Libri', 'type' => 'ondemand', 'media' => [jwOrgMedia('pub-a_I_VIDEO', 'A'), $sharedVideo]],
        ]],
        'VODFamily' => ['key' => 'VODFamily', 'name' => 'Famiglia', 'type' => 'container', 'subcategories' => [
            ['key' => 'FamilyWorship', 'name' => 'Culto', 'type' => 'ondemand', 'media' => [$sharedVideo, jwOrgMedia('pub-b_I_VIDEO', 'B')]],
        ]],
    ]);

    $media = resolve(JwOrgCatalog::class)->mediaInCategories(['VODBible', 'VODFamily']);

    expect(array_column($media, 'key'))->toBe(['pub-a_I_VIDEO', 'pub-shared_I_VIDEO', 'pub-b_I_VIDEO']);
});

it('serves a category from the cache after the first request', function (): void {
    fakeJwOrgCategories([
        'VODBible' => ['key' => 'VODBible', 'name' => 'La Bibbia', 'type' => 'container', 'subcategories' => []],
    ]);

    $catalog = resolve(JwOrgCatalog::class);
    $catalog->category('VODBible');
    $catalog->category('VODBible');

    Http::assertSentCount(1);
});

it('throws when the JW.org API fails', function (): void {
    Http::preventStrayRequests();
    Http::fake(['b.jw-cdn.org/apis/mediator/v1/categories/I/VODBible?*' => Http::response(status: 503)]);

    resolve(JwOrgCatalog::class)->category('VODBible');
})->throws(RuntimeException::class, 'JW.org API returned status 503 for category VODBible');

it('looks up a single media item and caches it', function (): void {
    Http::preventStrayRequests();
    Http::fake(['b.jw-cdn.org/apis/mediator/v1/media-items/I/pub-ebtv_103_VIDEO?*' => Http::response([
        'media' => [jwOrgMedia('pub-ebtv_103_VIDEO', 'Si può essere amici di Dio?')],
    ])]);

    $catalog = resolve(JwOrgCatalog::class);
    $catalog->mediaItem('pub-ebtv_103_VIDEO');
    $item = $catalog->mediaItem('pub-ebtv_103_VIDEO');

    expect($item)->title->toBe('Si può essere amici di Dio?');
    Http::assertSentCount(1);
});

it('returns null for a media key JW.org does not know, and remembers it', function (): void {
    Http::preventStrayRequests();
    Http::fake(['b.jw-cdn.org/apis/mediator/v1/media-items/I/pub-unknown_VIDEO?*' => Http::response(['media' => []])]);

    $catalog = resolve(JwOrgCatalog::class);

    expect($catalog->mediaItem('pub-unknown_VIDEO'))->toBeNull()
        ->and($catalog->mediaItem('pub-unknown_VIDEO'))->toBeNull();
    Http::assertSentCount(1);
});

it('looks up several media items at once, skipping the ones it cannot describe', function (): void {
    Http::preventStrayRequests();
    Http::fake([
        'b.jw-cdn.org/apis/mediator/v1/media-items/I/pub-a_VIDEO?*' => Http::response(['media' => [jwOrgMedia('pub-a_VIDEO', 'Video A')]]),
        'b.jw-cdn.org/apis/mediator/v1/media-items/I/pub-b_VIDEO?*' => Http::response(status: 503),
    ]);

    $catalog = resolve(JwOrgCatalog::class);
    $items = $catalog->mediaItems(['pub-a_VIDEO', 'pub-b_VIDEO', 'pub-a_VIDEO']);
    $catalog->mediaItems(['pub-a_VIDEO', 'pub-b_VIDEO']);

    expect($items)->toHaveKeys(['pub-a_VIDEO'])->not->toHaveKey('pub-b_VIDEO')
        ->and($items['pub-a_VIDEO']['title'])->toBe('Video A');
    Http::assertSentCount(2);
});
