<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Estrae l'identificativo stabile (naturalKey) di un contenuto JW.org dal suo URL.
 *
 * Esempi:
 * - https://www.jw.org/it/biblioteca-digitale/video/#it/mediaitems/VODBible/pub-nwtsv_I_1_VIDEO → pub-nwtsv_I_1_VIDEO
 * - https://www.jw.org/it/.../pub-osg_I_119_AUDIO → pub-osg_I_119_AUDIO
 */
class JwMediaKey
{
    private const string MEDIA_ITEMS_PATTERN = '#[a-z]{2,3}/mediaitems/[^/]+/([A-Za-z0-9_-]+)#i';

    private const string SUFFIX_PATTERN = '#([A-Za-z0-9_-]+_(?:VIDEO|AUDIO))(?:[/?\#]|$)#i';

    public static function fromUrl(string $url): ?string
    {
        if (preg_match(self::MEDIA_ITEMS_PATTERN, $url, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match(self::SUFFIX_PATTERN, $url, $matches) === 1) {
            return $matches[1];
        }

        return null;
    }
}
