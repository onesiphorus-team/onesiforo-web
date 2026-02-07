<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Extracts video metadata from JW.org section/category URLs using the Mediator API.
 */
class JwOrgMediaExtractor
{
    private const string API_BASE = 'https://b.jw-cdn.org/apis/mediator/v1/categories';

    /**
     * Language mapping from jw.org URL codes to Mediator API codes.
     *
     * @var array<string, string>
     */
    private const array LANGUAGE_MAP = [
        'it' => 'I',
        'en' => 'E',
        'es' => 'S',
        'pt' => 'T',
        'fr' => 'F',
        'de' => 'X',
        'ru' => 'U',
        'ja' => 'J',
        'ko' => 'KO',
        'zh' => 'CHS',
    ];

    /**
     * Extract videos from a JW.org section URL.
     *
     * @return array{category_name: string, videos: list<array{title: string, url: string, duration_seconds: int, duration_formatted: string}>, total_count: int, total_duration_formatted: string}
     *
     * @throws InvalidArgumentException If the URL cannot be parsed
     * @throws ConnectionException If the JW.org API is unreachable
     */
    public function extractFromUrl(string $sectionUrl): array
    {
        ['language' => $language, 'category_key' => $categoryKey] = $this->parseUrl($sectionUrl);

        $apiLang = $this->mapLanguageCode($language);
        $data = $this->fetchCategory($apiLang, $categoryKey);

        $categoryName = $data['category']['name'] ?? $categoryKey;
        $videos = $this->extractMedia($data, $language);

        $totalSeconds = array_sum(array_column($videos, 'duration_seconds'));

        return [
            'category_name' => $categoryName,
            'videos' => $videos,
            'total_count' => count($videos),
            'total_duration_formatted' => $this->formatDuration($totalSeconds),
        ];
    }

    /**
     * Parse a JW.org section URL to extract language and category key.
     *
     * @return array{language: string, category_key: string}
     */
    public function parseUrl(string $url): array
    {
        $parsed = parse_url($url);

        // Extract language from path (e.g., /it/biblioteca/video/)
        $language = 'it';
        if (isset($parsed['path']) && preg_match('#^/([a-z]{2,3})/#', $parsed['path'], $matches)) {
            $language = $matches[1];
        }

        // Extract category key from fragment (e.g., #it/categories/VODBible)
        $fragment = $parsed['fragment'] ?? '';
        throw_unless(preg_match('#[a-z]{2,3}/categories/([a-zA-Z0-9_-]+)#i', $fragment, $matches), InvalidArgumentException::class, "Cannot extract category key from URL: {$url}");

        return [
            'language' => $language,
            'category_key' => $matches[1],
        ];
    }

    /**
     * Map jw.org language code to Mediator API language code.
     */
    private function mapLanguageCode(string $jwLang): string
    {
        return self::LANGUAGE_MAP[strtolower($jwLang)] ?? strtoupper($jwLang);
    }

    /**
     * Fetch category data from the Mediator API.
     *
     * @return array<string, mixed>
     *
     * @throws ConnectionException
     */
    private function fetchCategory(string $apiLang, string $categoryKey): array
    {
        $url = self::API_BASE."/{$apiLang}/{$categoryKey}";

        $response = Http::timeout(15)
            ->get($url, ['detailed' => 1]);

        if ($response->failed()) {
            throw new RuntimeException("JW.org API returned status {$response->status()} for category {$categoryKey}");
        }

        return $response->json();
    }

    /**
     * Extract media items from API response, traversing subcategories.
     *
     * @param  array<string, mixed>  $data
     * @return list<array{title: string, url: string, duration_seconds: int, duration_formatted: string}>
     */
    private function extractMedia(array $data, string $language): array
    {
        $videos = [];
        $category = $data['category'] ?? [];

        // Direct media items
        foreach ($category['media'] ?? [] as $media) {
            $video = $this->mapMediaItem($media, $language);
            if ($video !== null) {
                $videos[] = $video;
            }
        }

        // Media items in subcategories
        foreach ($category['subcategories'] ?? [] as $subcategory) {
            foreach ($subcategory['media'] ?? [] as $media) {
                $video = $this->mapMediaItem($media, $language);
                if ($video !== null) {
                    $videos[] = $video;
                }
            }
        }

        return $videos;
    }

    /**
     * Map a single media item from the API to our format.
     *
     * @param  array<string, mixed>  $media
     * @return array{title: string, url: string, duration_seconds: int, duration_formatted: string}|null
     */
    private function mapMediaItem(array $media, string $language): ?array
    {
        $naturalKey = $media['naturalKey'] ?? null;
        if ($naturalKey === null) {
            return null;
        }

        $durationSeconds = (int) ($media['duration'] ?? 0);

        return [
            'title' => $media['title'] ?? $naturalKey,
            'url' => $this->buildVideoUrl($naturalKey, $language),
            'duration_seconds' => $durationSeconds,
            'duration_formatted' => $this->formatDuration($durationSeconds),
        ];
    }

    /**
     * Build a JW.org video page URL from a naturalKey.
     */
    private function buildVideoUrl(string $naturalKey, string $language): string
    {
        // Determine category from naturalKey (e.g., pub-nwtsv_I_1_VIDEO → VODBible)
        // We use a generic mediaitems path that JW.org resolves
        return "https://www.jw.org/{$language}/biblioteca/video/#{$language}/mediaitems/LatestVideos/{$naturalKey}";
    }

    /**
     * Format seconds into a human-readable duration string.
     */
    private function formatDuration(int $totalSeconds): string
    {
        if ($totalSeconds <= 0) {
            return '0m';
        }

        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        if ($hours > 0) {
            return $minutes > 0 ? "{$hours}h {$minutes}m" : "{$hours}h";
        }

        if ($minutes > 0) {
            return $seconds > 0 ? "{$minutes}m {$seconds}s" : "{$minutes}m";
        }

        return "{$seconds}s";
    }
}
