<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\ActivityTimelineKind;
use Carbon\CarbonInterface;

final readonly class ActivityTimelineEntry
{
    public function __construct(
        public ActivityTimelineKind $kind,
        public CarbonInterface $startedAt,
        public ?CarbonInterface $endedAt,
        public string $label,
        public string $iconName,
        public ?string $metadata = null,
    ) {}

    public function isInProgress(): bool
    {
        return ! $this->endedAt instanceof CarbonInterface;
    }
}
