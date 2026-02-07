<?php

declare(strict_types=1);

use App\Services\JwOrgMediaExtractor;
use Illuminate\Support\Facades\Http;

beforeEach(function (): void {
    $this->extractor = resolve(JwOrgMediaExtractor::class);
});

test('extracts videos from valid category API response', function (): void {
    Http::fake([
        'b.jw-cdn.org/apis/mediator/v1/categories/I/VODBible*' => Http::response([
            'category' => [
                'key' => 'VODBible',
                'name' => 'La Bibbia',
                'media' => [
                    [
                        'naturalKey' => 'pub-nwtsv_I_1_VIDEO',
                        'title' => 'Introduzione alla Bibbia',
                        'duration' => 327.723,
                    ],
                ],
                'subcategories' => [
                    [
                        'key' => 'BibleBooks',
                        'media' => [
                            [
                                'naturalKey' => 'pub-nwtsv_I_2_VIDEO',
                                'title' => 'Genesi',
                                'duration' => 180.5,
                            ],
                        ],
                    ],
                ],
            ],
        ]),
    ]);

    $result = $this->extractor->extractFromUrl(
        'https://www.jw.org/it/biblioteca/video/#it/categories/VODBible'
    );

    expect($result['category_name'])->toBe('La Bibbia');
    expect($result['total_count'])->toBe(2);
    expect($result['videos'])->toHaveCount(2);
    expect($result['videos'][0]['title'])->toBe('Introduzione alla Bibbia');
    expect($result['videos'][0]['duration_seconds'])->toBe(327);
    expect($result['videos'][1]['title'])->toBe('Genesi');
});

test('handles empty category with no media', function (): void {
    Http::fake([
        'b.jw-cdn.org/apis/mediator/v1/categories/I/EmptyCategory*' => Http::response([
            'category' => [
                'key' => 'EmptyCategory',
                'name' => 'Categoria Vuota',
                'media' => [],
                'subcategories' => [],
            ],
        ]),
    ]);

    $result = $this->extractor->extractFromUrl(
        'https://www.jw.org/it/biblioteca/video/#it/categories/EmptyCategory'
    );

    expect($result['total_count'])->toBe(0);
    expect($result['videos'])->toBeEmpty();
});

test('handles API error with exception', function (): void {
    Http::fake([
        'b.jw-cdn.org/apis/mediator/v1/categories/I/NonExistent*' => Http::response('Not Found', 404),
    ]);

    $this->extractor->extractFromUrl(
        'https://www.jw.org/it/biblioteca/video/#it/categories/NonExistent'
    );
})->throws(RuntimeException::class);

test('parses URL correctly extracting language and category key', function (): void {
    $result = $this->extractor->parseUrl(
        'https://www.jw.org/it/biblioteca/video/#it/categories/VODBible'
    );

    expect($result['language'])->toBe('it');
    expect($result['category_key'])->toBe('VODBible');
});

test('parses URL with English language', function (): void {
    $result = $this->extractor->parseUrl(
        'https://www.jw.org/en/library/videos/#en/categories/VODMinistryTools'
    );

    expect($result['language'])->toBe('en');
    expect($result['category_key'])->toBe('VODMinistryTools');
});

test('throws exception for invalid URL without categories fragment', function (): void {
    $this->extractor->parseUrl('https://www.jw.org/it/biblioteca/video/#it/mediaitems/VODBible/test');
})->throws(InvalidArgumentException::class);

test('builds correct video URLs with language', function (): void {
    Http::fake([
        'b.jw-cdn.org/apis/mediator/v1/categories/I/Test*' => Http::response([
            'category' => [
                'key' => 'Test',
                'name' => 'Test',
                'media' => [
                    [
                        'naturalKey' => 'pub-test_VIDEO',
                        'title' => 'Test Video',
                        'duration' => 60,
                    ],
                ],
                'subcategories' => [],
            ],
        ]),
    ]);

    $result = $this->extractor->extractFromUrl(
        'https://www.jw.org/it/biblioteca/video/#it/categories/Test'
    );

    expect($result['videos'][0]['url'])
        ->toContain('jw.org/it/')
        ->toContain('pub-test_VIDEO');
});

test('formats total duration correctly', function (): void {
    Http::fake([
        'b.jw-cdn.org/apis/mediator/v1/categories/I/LongCategory*' => Http::response([
            'category' => [
                'key' => 'LongCategory',
                'name' => 'Long Category',
                'media' => [
                    ['naturalKey' => 'v1', 'title' => 'V1', 'duration' => 3600],
                    ['naturalKey' => 'v2', 'title' => 'V2', 'duration' => 1800],
                    ['naturalKey' => 'v3', 'title' => 'V3', 'duration' => 900],
                ],
                'subcategories' => [],
            ],
        ]),
    ]);

    $result = $this->extractor->extractFromUrl(
        'https://www.jw.org/it/biblioteca/video/#it/categories/LongCategory'
    );

    expect($result['total_duration_formatted'])->toBe('1h 45m');
});

test('validates section URL format via JwOrgSectionUrl rule', function (): void {
    $rule = new App\Rules\JwOrgSectionUrl;
    $errors = [];

    $rule->validate('url', 'https://www.jw.org/it/biblioteca/video/#it/categories/VODBible', function ($message) use (&$errors): void {
        $errors[] = $message;
    });

    expect($errors)->toBeEmpty();
});

test('JwOrgSectionUrl rejects non-category URLs', function (): void {
    $rule = new App\Rules\JwOrgSectionUrl;
    $errors = [];

    $rule->validate('url', 'https://www.jw.org/it/biblioteca/video/#it/mediaitems/VODBible/test', function ($message) use (&$errors): void {
        $errors[] = $message;
    });

    expect($errors)->not->toBeEmpty();
});
