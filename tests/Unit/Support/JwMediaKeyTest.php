<?php

declare(strict_types=1);

use App\Support\JwMediaKey;

it('extracts the JW.org natural key from a media URL', function (string $url, ?string $expectedKey): void {
    expect(JwMediaKey::fromUrl($url))->toBe($expectedKey);
})->with([
    'video mediaitems fragment' => ['https://www.jw.org/it/biblioteca-digitale/video/#it/mediaitems/VODBible/pub-nwtsv_I_1_VIDEO', 'pub-nwtsv_I_1_VIDEO'],
    'audio mediaitems fragment' => ['https://www.jw.org/it/biblioteca-digitale/audio/#it/mediaitems/AudioOriginalSongs/pub-osg_I_119_AUDIO', 'pub-osg_I_119_AUDIO'],
    'docid key in fragment' => ['https://www.jw.org/it/biblioteca/video/#it/mediaitems/StudioTalks/docid-702024101_1_VIDEO', 'docid-702024101_1_VIDEO'],
    'key in the path' => ['https://www.jw.org/finder?lank=pub-jwb-143_I_1_VIDEO', 'pub-jwb-143_I_1_VIDEO'],
    'plain mp4 file' => ['https://www.jw.org/media/video/some-file.mp4', null],
]);
