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
    'bare zoom.us with password' => ['https://zoom.us/j/123456789?pwd=abc'],
    'extra query parameters before the password' => ['https://us05web.zoom.us/j/123456789?omn=1&pwd=abc'],
    'webinar with password' => ['https://us05web.zoom.us/w/123456789?pwd=abc'],
    'password with dots and dashes' => ['https://us05web.zoom.us/j/85099838349?pwd=DATUPW-NZhaXX_UwkvuiA5OKWUfgbdTb.1'],
    'percent-encoded query' => ['https://us05web.zoom.us/j/123456789?pwd=abc%3D'],
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
    'trailing newline' => ["https://us05web.zoom.us/j/1234567890\n"],
    'trailing newline after the query' => ["https://us05web.zoom.us/j/1234567890?pwd=abc\n"],
    'leading space' => [' https://us05web.zoom.us/j/1234567890'],
    'trailing space' => ['https://us05web.zoom.us/j/1234567890 '],
    'space in the query' => ['https://us05web.zoom.us/j/1234567890?pwd=abc def'],
    'tab in the query' => ["https://us05web.zoom.us/j/1234567890?pwd=abc\tdef"],
    'carriage return' => ["https://us05web.zoom.us/j/1234567890\r\n"],
    'null byte' => ["https://us05web.zoom.us/j/1234567890\0"],
    'quote in the query' => ['https://us05web.zoom.us/j/1234567890?pwd=abc";rm'],
    'backslash before the host' => ['https://evil.com\\@zoom.us/j/1234567890'],
    'credentials in the URL' => ['https://user:pass@zoom.us/j/1234567890'],
    'non-ASCII characters' => ['https://zoom.us/j/1234567890?pwd=àbc'],
    'too long' => ['https://zoom.us/j/1234567890?pwd='.str_repeat('a', 2048)],
]);
