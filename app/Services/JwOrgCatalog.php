<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;

/**
 * Browses the JW.org video and audio library through the Mediator API.
 *
 * Fetching a category with `detailed=1` also returns the media of its direct
 * subcategories, so one request per category is enough to list everything below it.
 * Normalized categories are cached to keep the caregiver UI fast.
 *
 * @phpstan-type JwMediaItem array{key: string, title: string, media_type: string, duration_seconds: int, duration_formatted: string, thumbnail_url: string|null, url: string}
 * @phpstan-type JwSubcategory array{key: string, name: string, is_container: bool, media: list<JwMediaItem>}
 * @phpstan-type JwCategory array{key: string, name: string, is_container: bool, subcategories: list<JwSubcategory>, media: list<JwMediaItem>}
 */
class JwOrgCatalog
{
    /**
     * Root category of the library for each media type.
     *
     * @var array<string, string>
     */
    public const array ROOT_CATEGORIES = [
        'video' => 'VideoOnDemand',
        'audio' => 'Audio',
    ];

    private const string API_BASE = 'https://b.jw-cdn.org/apis/mediator/v1/categories';

    private const string API_LANGUAGE = 'I';

    private const string SITE_LANGUAGE = 'it';

    private const int CACHE_TTL_SECONDS = 6 * 60 * 60;

    private const string MEDIA_ITEMS_API_BASE = 'https://b.jw-cdn.org/apis/mediator/v1/media-items';

    private const int REQUEST_TIMEOUT_SECONDS = 15;

    /**
     * Kept short: the lookup happens while rendering the OnesiBox dashboard.
     */
    private const int MEDIA_ITEM_TIMEOUT_SECONDS = 5;

    /**
     * After a failed lookup, wait this long before asking JW.org again, so pages that
     * refresh every few seconds do not keep waiting on an unreachable API.
     */
    private const int FAILURE_RETRY_SECONDS = 2 * 60;

    /**
     * Preferred image formats for thumbnails, per media type.
     *
     * @var array<string, list<string>>
     */
    private const array THUMBNAIL_FORMATS = [
        'video' => ['wss', 'lss', 'pnr', 'sqr'],
        'audio' => ['sqr', 'cvr', 'wss', 'lss', 'pnr'],
    ];

    /**
     * @var list<string>
     */
    private const array THUMBNAIL_SIZES = ['sm', 'md', 'xs', 'lg'];

    /**
     * Get the root category (with its subcategories) for a media type.
     *
     * @return JwCategory
     */
    public function rootCategory(string $mediaType): array
    {
        throw_unless(isset(self::ROOT_CATEGORIES[$mediaType]), InvalidArgumentException::class, "Unsupported media type: {$mediaType}");

        return $this->category(self::ROOT_CATEGORIES[$mediaType]);
    }

    /**
     * Get a category with its subcategories and media.
     *
     * @return JwCategory
     *
     * @throws RuntimeException When the JW.org API is unreachable or returns an error
     */
    public function category(string $categoryKey): array
    {
        return Cache::remember(
            $this->cacheKey($categoryKey),
            self::CACHE_TTL_SECONDS,
            fn (): array => $this->normalizeCategory($this->toPayload($categoryKey, $this->request($categoryKey))),
        );
    }

    /**
     * Look up a single media item by its natural key (e.g. to describe what an OnesiBox is playing).
     *
     * @return JwMediaItem|null Null when JW.org does not know the key
     *
     * @throws RuntimeException|ConnectionException When the JW.org API is unreachable or returns an error
     */
    public function mediaItem(string $mediaKey): ?array
    {
        $item = Cache::remember(
            $this->mediaItemCacheKey($mediaKey),
            self::CACHE_TTL_SECONDS,
            function () use ($mediaKey): array|false {
                $response = Http::timeout(self::MEDIA_ITEM_TIMEOUT_SECONDS)
                    ->get($this->mediaItemEndpoint($mediaKey), ['clientType' => 'www']);

                throw_if($response->failed(), RuntimeException::class, "JW.org API returned status {$response->status()} for media item {$mediaKey}");

                return $this->toMediaItem($response);
            },
        );

        return $item === false ? null : $item;
    }

    /**
     * Look up several media items at once, fetching the uncached ones concurrently.
     *
     * Items JW.org does not know, or that cannot be fetched right now, are left out:
     * callers use this to decorate lists (titles, thumbnails) and fall back gracefully.
     *
     * @param  array<int, string>  $mediaKeys
     * @return array<string, JwMediaItem> Keyed by media key
     */
    public function mediaItems(array $mediaKeys): array
    {
        $mediaKeys = array_values(array_unique($mediaKeys));
        $missingKeys = array_values(array_filter(
            $mediaKeys,
            fn (string $mediaKey): bool => ! Cache::has($this->mediaItemCacheKey($mediaKey))
                && ! Cache::has($this->mediaItemFailureCacheKey($mediaKey)),
        ));

        if ($missingKeys !== []) {
            try {
                $responses = Http::pool(fn (Pool $pool): array => array_map(
                    fn (string $mediaKey) => $pool->as($mediaKey)
                        ->timeout(self::MEDIA_ITEM_TIMEOUT_SECONDS)
                        ->get($this->mediaItemEndpoint($mediaKey), ['clientType' => 'www']),
                    $missingKeys,
                ));
            } catch (ConnectionException) {
                $responses = [];
            }

            foreach ($missingKeys as $mediaKey) {
                $response = $responses[$mediaKey] ?? null;

                if ($response instanceof Response && $response->successful()) {
                    Cache::put($this->mediaItemCacheKey($mediaKey), $this->toMediaItem($response), self::CACHE_TTL_SECONDS);
                } else {
                    Cache::put($this->mediaItemFailureCacheKey($mediaKey), true, self::FAILURE_RETRY_SECONDS);
                }
            }
        }

        $items = [];

        foreach ($mediaKeys as $mediaKey) {
            if (Cache::has($this->mediaItemFailureCacheKey($mediaKey))) {
                continue;
            }

            try {
                $item = $this->mediaItem($mediaKey);
            } catch (RuntimeException|ConnectionException) {
                $item = null;
            }

            if ($item !== null) {
                $items[$mediaKey] = $item;
            }
        }

        return $items;
    }

    /**
     * Get every media item contained in the given categories and their direct subcategories,
     * without duplicates (the same video can appear in several categories).
     *
     * @param  list<string>  $categoryKeys
     * @return list<JwMediaItem>
     */
    public function mediaInCategories(array $categoryKeys): array
    {
        $this->warmCache($categoryKeys);

        $itemsByKey = [];

        foreach ($categoryKeys as $categoryKey) {
            $category = $this->category($categoryKey);

            foreach ($this->mediaOf($category) as $item) {
                $itemsByKey[$item['key']] ??= $item;
            }
        }

        return array_values($itemsByKey);
    }

    /**
     * Media of a category followed by the media of its direct subcategories.
     *
     * @param  JwCategory  $category
     * @return list<JwMediaItem>
     */
    public function mediaOf(array $category): array
    {
        $items = $category['media'];

        foreach ($category['subcategories'] as $subcategory) {
            array_push($items, ...$subcategory['media']);
        }

        return $items;
    }

    /**
     * Fetch the uncached categories concurrently and store them in the cache.
     *
     * @param  list<string>  $categoryKeys
     */
    private function warmCache(array $categoryKeys): void
    {
        $missingKeys = array_values(array_filter(
            array_unique($categoryKeys),
            fn (string $categoryKey): bool => ! Cache::has($this->cacheKey($categoryKey)),
        ));

        if (count($missingKeys) < 2) {
            return;
        }

        $responses = Http::pool(fn (Pool $pool): array => array_map(
            fn (string $categoryKey) => $pool->as($categoryKey)
                ->timeout(self::REQUEST_TIMEOUT_SECONDS)
                ->get($this->endpoint($categoryKey), $this->queryParameters()),
            $missingKeys,
        ));

        foreach ($responses as $categoryKey => $response) {
            if ($response instanceof Response && $response->successful()) {
                Cache::put(
                    $this->cacheKey((string) $categoryKey),
                    $this->normalizeCategory($this->toPayload((string) $categoryKey, $response)),
                    self::CACHE_TTL_SECONDS,
                );
            }
        }
    }

    /**
     * @return JwMediaItem|false False when JW.org does not know the item
     */
    private function toMediaItem(Response $response): array|false
    {
        $media = $response->json('media.0');

        return is_array($media) && isset($media['naturalKey']) ? $this->normalizeMedia($media, 'mediaitems') : false;
    }

    private function mediaItemEndpoint(string $mediaKey): string
    {
        return self::MEDIA_ITEMS_API_BASE.'/'.self::API_LANGUAGE.'/'.rawurlencode($mediaKey);
    }

    private function mediaItemCacheKey(string $mediaKey): string
    {
        return 'jworg:media-item:'.self::API_LANGUAGE.':'.$mediaKey;
    }

    private function mediaItemFailureCacheKey(string $mediaKey): string
    {
        return 'jworg:media-item-failed:'.self::API_LANGUAGE.':'.$mediaKey;
    }

    private function request(string $categoryKey): Response
    {
        return Http::timeout(self::REQUEST_TIMEOUT_SECONDS)
            ->get($this->endpoint($categoryKey), $this->queryParameters());
    }

    /**
     * @return array<string, mixed>
     */
    private function toPayload(string $categoryKey, Response $response): array
    {
        if ($response->failed()) {
            throw new RuntimeException("JW.org API returned status {$response->status()} for category {$categoryKey}");
        }

        $category = $response->json('category');

        throw_unless(is_array($category), RuntimeException::class, "JW.org API returned no category for {$categoryKey}");

        return $category;
    }

    /**
     * @param  array<string, mixed>  $category
     * @return JwCategory
     */
    private function normalizeCategory(array $category): array
    {
        $categoryKey = (string) ($category['key'] ?? '');

        return [
            'key' => $categoryKey,
            'name' => (string) ($category['name'] ?? $categoryKey),
            'is_container' => ($category['type'] ?? null) === 'container',
            'subcategories' => array_values(array_map(
                fn (array $subcategory): array => [
                    'key' => (string) $subcategory['key'],
                    'name' => (string) ($subcategory['name'] ?? $subcategory['key']),
                    'is_container' => ($subcategory['type'] ?? null) === 'container',
                    'media' => $this->normalizeMediaList($subcategory['media'] ?? [], (string) $subcategory['key']),
                ],
                array_filter($category['subcategories'] ?? [], fn (mixed $subcategory): bool => is_array($subcategory) && isset($subcategory['key'])),
            )),
            'media' => $this->normalizeMediaList($category['media'] ?? [], $categoryKey),
        ];
    }

    /**
     * @param  array<int, mixed>  $mediaList
     * @return list<JwMediaItem>
     */
    private function normalizeMediaList(array $mediaList, string $categoryKey): array
    {
        $items = [];

        foreach ($mediaList as $media) {
            if (is_array($media) && isset($media['naturalKey'])) {
                $items[] = $this->normalizeMedia($media, $categoryKey);
            }
        }

        return $items;
    }

    /**
     * The item key is the language-agnostic natural key (e.g. pub-ebtv_103_VIDEO): it is what
     * jw.org puts in its own URLs and what the Mediator `media-items` endpoint, used by the
     * OnesiBox player, can resolve. The language-specific one (pub-ebtv_I_103_VIDEO) returns no media.
     *
     * @param  array<string, mixed>  $media
     * @return JwMediaItem
     */
    private function normalizeMedia(array $media, string $categoryKey): array
    {
        $naturalKey = (string) ($media['languageAgnosticNaturalKey'] ?? $media['naturalKey']);
        $mediaType = ($media['type'] ?? null) === 'audio' || str_ends_with($naturalKey, '_AUDIO') ? 'audio' : 'video';
        $durationSeconds = (int) round((float) ($media['duration'] ?? 0));
        $urlCategory = (string) ($media['primaryCategory'] ?? $categoryKey);

        return [
            'key' => $naturalKey,
            'title' => (string) ($media['title'] ?? $naturalKey),
            'media_type' => $mediaType,
            'duration_seconds' => $durationSeconds,
            'duration_formatted' => $this->formatDuration($durationSeconds),
            'thumbnail_url' => $this->thumbnailUrl($media['images'] ?? [], $mediaType),
            'url' => sprintf(
                'https://www.jw.org/%s/biblioteca-digitale/%s/#%s/mediaitems/%s/%s',
                self::SITE_LANGUAGE,
                $mediaType,
                self::SITE_LANGUAGE,
                $urlCategory,
                $naturalKey,
            ),
        ];
    }

    /**
     * @param  mixed  $images  Mediator image map, e.g. ['wss' => ['sm' => 'https://...']]
     */
    private function thumbnailUrl(mixed $images, string $mediaType): ?string
    {
        if (! is_array($images)) {
            return null;
        }

        foreach (self::THUMBNAIL_FORMATS[$mediaType] as $format) {
            foreach (self::THUMBNAIL_SIZES as $size) {
                $url = $images[$format][$size] ?? null;

                if (is_string($url) && str_starts_with($url, 'https://')) {
                    return $url;
                }
            }
        }

        return null;
    }

    private function formatDuration(int $totalSeconds): string
    {
        $hours = intdiv($totalSeconds, 3600);
        $minutes = intdiv($totalSeconds % 3600, 60);
        $seconds = $totalSeconds % 60;

        return $hours > 0
            ? sprintf('%d:%02d:%02d', $hours, $minutes, $seconds)
            : sprintf('%d:%02d', $minutes, $seconds);
    }

    private function endpoint(string $categoryKey): string
    {
        return self::API_BASE.'/'.self::API_LANGUAGE.'/'.rawurlencode($categoryKey);
    }

    /**
     * @return array<string, int|string>
     */
    private function queryParameters(): array
    {
        return ['detailed' => 1, 'clientType' => 'www'];
    }

    private function cacheKey(string $categoryKey): string
    {
        return 'jworg:catalog:'.self::API_LANGUAGE.':'.$categoryKey;
    }
}
