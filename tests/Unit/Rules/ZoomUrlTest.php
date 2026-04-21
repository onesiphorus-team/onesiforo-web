<?php

declare(strict_types=1);

use App\Rules\ZoomUrl;
use Illuminate\Support\Facades\Validator;

function validateZoomUrl(mixed $value): bool
{
    return Validator::make(
        ['url' => $value],
        ['url' => [new ZoomUrl]],
    )->passes();
}

it('accepts valid Zoom URLs', function (string $url): void {
    expect(validateZoomUrl($url))->toBeTrue();
})->with([
    'meeting id' => ['https://us05web.zoom.us/j/1234567890'],
    'meeting with password' => ['https://us05web.zoom.us/j/1234567890?pwd=abc123'],
    'webinar' => ['https://zoom.us/w/987654321'],
    'signed meeting' => ['https://us02web.zoom.us/s/5551112222'],
    'bare zoom.us' => ['https://zoom.us/j/123'],
    'hyphenated subdomain' => ['https://us05web-lb.zoom.us/j/1234567890'],
    'trailing slash' => ['https://us05web.zoom.us/j/1234567890/'],
    'uppercase host' => ['https://US05WEB.ZOOM.US/j/1234567890'],
    'with fragment' => ['https://us05web.zoom.us/j/1234567890#anchor'],
]);

it('rejects invalid URLs', function (mixed $url): void {
    expect(validateZoomUrl($url))->toBeFalse();
})->with([
    'non-string' => [123],
    'null' => [null],
    'http scheme' => ['http://us05web.zoom.us/j/1234567890'],
    'wrong domain' => ['https://zoombies.com/j/1234567890'],
    'domain lookalike' => ['https://zoom.us.evil.com/j/1234567890'],
    'javascript scheme' => ['javascript:alert(1)'],
    'non-digit meeting id' => ['https://zoom.us/j/abcdef'],
    'missing meeting id' => ['https://zoom.us/j/'],
    'unknown path' => ['https://zoom.us/my/meeting/1234567890'],
    'no path' => ['https://zoom.us'],
    'ftp scheme' => ['ftp://zoom.us/j/1234567890'],
    'garbage' => ['not-a-url'],
]);
