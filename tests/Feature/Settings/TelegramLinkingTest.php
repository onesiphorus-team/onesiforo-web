<?php

declare(strict_types=1);

use App\Livewire\Settings\Telegram;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

function enableTelegramLinking(): void
{
    config([
        'services.telegram-bot-api.token' => 'bot-token',
        'services.telegram-bot-api.linking_enabled' => true,
        'services.telegram-bot-api.bot_username' => 'OnesiforoBot',
        'services.telegram-bot-api.webhook_secret' => 'webhook-secret',
    ]);
}

/**
 * @param  array<string, mixed>  $message
 */
function telegramUpdate(array $message, string $secret = 'webhook-secret'): Illuminate\Testing\TestResponse
{
    return test()->postJson(route('telegram.webhook'), ['update_id' => 1, 'message' => $message], ['X-Telegram-Bot-Api-Secret-Token' => $secret]);
}

it('is off by default: no page, no menu entry, no webhook', function (): void {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('telegram.edit'))->assertNotFound();
    $this->actingAs($user)->get(route('appearance.edit'))->assertDontSee(route('telegram.edit'));
    telegramUpdate(['chat' => ['id' => 1, 'type' => 'private'], 'text' => '/start x'])->assertNotFound();
});

it('links the account when the user presses Start in the bot from their personal link', function (): void {
    enableTelegramLinking();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
    $user = User::factory()->create(['telegram_chat_id' => null]);

    $page = Livewire::actingAs($user)->test(Telegram::class)->call('startLinking');
    $token = $page->get('linkToken');

    expect($page->get('linkUrl'))->toBe("https://t.me/OnesiforoBot?start={$token}");

    telegramUpdate(['chat' => ['id' => 123456789, 'type' => 'private'], 'text' => "/start {$token}"])->assertOk();

    expect($user->refresh()->telegram_chat_id)->toBe('123456789')
        ->and(Cache::has(Telegram::cacheKey($token)))->toBeFalse();
    $page->call('$refresh')->assertSet('isConnected', true)->assertSee('Telegram è collegato');
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/botbot-token/sendMessage') && $request['chat_id'] === 123456789);
});

it('refuses updates without the webhook secret', function (): void {
    enableTelegramLinking();
    $user = User::factory()->create();
    Cache::put(Telegram::cacheKey('token'), $user->id, 60);

    telegramUpdate(['chat' => ['id' => 1, 'type' => 'private'], 'text' => '/start token'], 'wrong')->assertForbidden();

    expect($user->refresh()->telegram_chat_id)->toBeNull();
});

it('does not link with an expired link, and tells the user how to retry', function (): void {
    enableTelegramLinking();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);

    telegramUpdate(['chat' => ['id' => 1, 'type' => 'private'], 'text' => '/start expired'])->assertOk();

    Http::assertSent(fn ($request): bool => str_contains((string) $request['text'], 'link è scaduto'));
});

it('ignores group chats, so a group cannot take over an account', function (): void {
    enableTelegramLinking();
    $user = User::factory()->create(['telegram_chat_id' => null]);
    Cache::put(Telegram::cacheKey('token'), $user->id, 60);

    telegramUpdate(['chat' => ['id' => -100123, 'type' => 'supergroup'], 'text' => '/start token'])->assertOk();

    expect($user->refresh()->telegram_chat_id)->toBeNull();
});

it('lets the user disconnect Telegram', function (): void {
    enableTelegramLinking();
    $user = User::factory()->create(['telegram_chat_id' => '123']);

    Livewire::actingAs($user)->test(Telegram::class)
        ->assertSee('Telegram è collegato')
        ->call('disconnect')
        ->assertSee('Collega Telegram');

    expect($user->refresh()->telegram_chat_id)->toBeNull();
});

it('registers the webhook with Telegram only when asked', function (): void {
    enableTelegramLinking();
    Http::fake(['api.telegram.org/*' => Http::response(['ok' => true, 'result' => true])]);

    $this->artisan('telegram:set-webhook', ['--force' => true])->assertSuccessful();

    Http::assertSent(fn ($request): bool => str_ends_with($request->url(), '/botbot-token/setWebhook')
        && $request['url'] === route('telegram.webhook')
        && $request['secret_token'] === 'webhook-secret');
});
