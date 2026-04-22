<?php

declare(strict_types=1);

use App\Rules\JwStreamUrl;

function validateJwStreamUrl(mixed $value): ?string
{
    $rule = new JwStreamUrl;
    $error = null;
    $rule->validate('url', $value, function ($message) use (&$error) {
        $error = (string) $message;
    });

    return $error;
}

it('accepts a stream.jw.org share link', function () {
    expect(validateJwStreamUrl('https://stream.jw.org/6311-4713-5379-2156'))->toBeNull();
});

it('accepts stream.jw.org /home path', function () {
    expect(validateJwStreamUrl('https://stream.jw.org/home'))->toBeNull();
});

it('accepts stream.jw.org /home?playerOpen=true', function () {
    expect(validateJwStreamUrl('https://stream.jw.org/home?playerOpen=true'))->toBeNull();
});

it('rejects http (no HTTPS)', function () {
    expect(validateJwStreamUrl('http://stream.jw.org/x'))->not->toBeNull();
});

it('rejects subdomain-injection attack', function () {
    expect(validateJwStreamUrl('https://stream.jw.org.evil.com/x'))->not->toBeNull();
});

it('rejects fake-stream subdomain', function () {
    expect(validateJwStreamUrl('https://fake-stream.jw.org/x'))->not->toBeNull();
});

it('rejects www.jw.org (wrong domain for this rule)', function () {
    expect(validateJwStreamUrl('https://www.jw.org/mediaitems/x'))->not->toBeNull();
});

it('rejects empty string', function () {
    expect(validateJwStreamUrl(''))->not->toBeNull();
});

it('rejects null', function () {
    expect(validateJwStreamUrl(null))->not->toBeNull();
});

it('rejects non-standard port', function () {
    expect(validateJwStreamUrl('https://stream.jw.org:9999/x'))->not->toBeNull();
});

it('rejects URL longer than 2048 characters', function () {
    $longPath = str_repeat('a', 3000);
    expect(validateJwStreamUrl("https://stream.jw.org/{$longPath}"))->not->toBeNull();
});
