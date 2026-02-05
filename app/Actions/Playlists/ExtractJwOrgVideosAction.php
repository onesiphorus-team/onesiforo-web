<?php

declare(strict_types=1);

namespace App\Actions\Playlists;

use App\Services\JwOrgMediaExtractor;

/**
 * Extracts video metadata from a JW.org section URL.
 */
class ExtractJwOrgVideosAction
{
    public function __construct(
        private readonly JwOrgMediaExtractor $extractor,
    ) {}

    /**
     * Execute the action to extract videos from a JW.org section.
     *
     * @return array{category_name: string, videos: list<array{title: string, url: string, duration_seconds: int, duration_formatted: string}>, total_count: int, total_duration_formatted: string}
     */
    public function execute(string $sectionUrl): array
    {
        return $this->extractor->extractFromUrl($sectionUrl);
    }
}
