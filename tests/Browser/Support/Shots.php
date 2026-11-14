<?php

declare(strict_types=1);

namespace Tests\Browser\Support;

use Pest\Browser\Api\AwaitableWebpage;
use Pest\Browser\Api\PendingAwaitablePage;
use Throwable;

use function visit;

/**
 * Helpers shared by the browser tests: opening a page at the two reference
 * viewports and saving screenshots with descriptive file names.
 */
final class Shots
{
    public const DESKTOP = [1440, 900];

    public const MOBILE = [390, 844];

    private const PHONE_USER_AGENT = 'Mozilla/5.0 (Linux; Android 14; Pixel 8) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/130.0 Mobile Safari/537.36';

    /**
     * Open a URL at the given device ("desktop" or "mobile").
     */
    public static function open(string $url, string $device): AwaitableWebpage|PendingAwaitablePage
    {
        if ($device === 'mobile') {
            return visit($url)->on()->mobile()->withUserAgent(self::PHONE_USER_AGENT)->resize(...self::MOBILE);
        }

        return visit($url)->on()->desktop()->resize(...self::DESKTOP);
    }

    /**
     * Save a screenshot as "{name}-{device}.png" in tests/Browser/Screenshots.
     */
    public static function take(AwaitableWebpage|PendingAwaitablePage $page, string $name, string $device, bool $fullPage = true): void
    {
        $page->screenshot($fullPage, "{$name}-{$device}");
    }

    /**
     * Assert that the page does not scroll horizontally.
     */
    public static function assertNoHorizontalScroll(AwaitableWebpage|PendingAwaitablePage $page): void
    {
        $page->assertScript('document.documentElement.scrollWidth <= window.innerWidth');
    }

    /**
     * Retry an assertion until it passes: Livewire answers asynchronously, and assertSee() does not wait.
     */
    public static function eventually(callable $assertion, float $seconds = 10): void
    {
        $deadline = microtime(true) + $seconds;

        while (true) {
            try {
                $assertion();

                return;
            } catch (Throwable $e) {
                if (microtime(true) > $deadline) {
                    throw $e;
                }

                \Pest\Browser\Execution::instance()->wait(0.25);
            }
        }
    }

    public static function see(AwaitableWebpage|PendingAwaitablePage $page, string $text, float $seconds = 10): void
    {
        self::eventually(fn () => $page->assertSee($text), $seconds);
    }

    public static function gone(AwaitableWebpage|PendingAwaitablePage $page, string $text, float $seconds = 10): void
    {
        self::eventually(fn () => $page->assertDontSee($text), $seconds);
    }

    /**
     * Click the first VISIBLE element matching a CSS selector (the bottom bar renders a desktop and a mobile copy).
     */
    public static function tap(AwaitableWebpage|PendingAwaitablePage $page, string $css): void
    {
        $page->click("{$css}:visible");
    }
}
