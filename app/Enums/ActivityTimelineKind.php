<?php

declare(strict_types=1);

namespace App\Enums;

enum ActivityTimelineKind: string
{
    case Playback = 'playback';
    case Meeting = 'meeting';
}
