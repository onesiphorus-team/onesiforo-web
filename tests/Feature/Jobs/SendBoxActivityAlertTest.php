<?php

declare(strict_types=1);

use App\Enums\OnesiBoxPermission;
use App\Jobs\SendBoxActivityAlert;
use App\Models\OnesiBox;
use App\Models\User;
use App\Notifications\BoxActivityNotification;
use App\Services\BoxAlerts\BoxActivityAlerts;
use App\Services\JwOrgCatalog;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use NotificationChannels\Telegram\Exceptions\CouldNotSendNotification;

beforeEach(function (): void {
    config(['services.telegram-bot-api.token' => 'bot-token', 'services.telegram-bot-api.support_chat_id' => null]);
    $this->box = OnesiBox::factory()->online()->create()->refresh();
});

function telegramIsDown(): void
{
    Notification::shouldReceive('sendNow')->andThrow(CouldNotSendNotification::couldNotCommunicateWithTelegram(
        'cURL error 28: Operation timed out for https://api.telegram.org/botbot-token/sendMessage',
    ));
}

it('queues one job for each chat, so a retry never writes again to a chat that got the alert', function (): void {
    Queue::fake();
    foreach (['-100555', '777'] as $chatId) {
        $this->box->caregivers()->attach(User::factory()->create(['telegram_chat_id' => $chatId]), ['permission' => OnesiBoxPermission::Full->value]);
    }

    resolve(BoxActivityAlerts::class)->send($this->box, 'Spento o non connesso.');

    Queue::assertPushed(SendBoxActivityAlert::class, 2);
    Queue::assertPushed(SendBoxActivityAlert::class, fn (SendBoxActivityAlert $job): bool => $job->chatId === '-100555');
    Queue::assertPushed(SendBoxActivityAlert::class, fn (SendBoxActivityAlert $job): bool => $job->chatId === '777');
});

it('sends the alert to its chat', function (): void {
    Notification::fake();

    (new SendBoxActivityAlert($this->box->id, 'Spento o non connesso.', '777'))->withFakeQueueInteractions()->handle(resolve(JwOrgCatalog::class));

    Notification::assertSentOnDemand(
        BoxActivityNotification::class,
        fn (BoxActivityNotification $notification, array $channels, object $notifiable): bool => $notifiable->routes['telegram'] === '777'
            && str_contains($notification->html, 'Spento o non connesso.'),
    );
});

it('tries again later when Telegram cannot be reached, without logging the bot token', function (): void {
    telegramIsDown();
    Log::spy();

    $job = (new SendBoxActivityAlert($this->box->id, 'Spento o non connesso.', '777'))->withFakeQueueInteractions();
    $job->handle(resolve(JwOrgCatalog::class));

    $job->assertReleased(delay: 30);
    Log::shouldHaveReceived('warning')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => $context['chat_id'] === '777'
            && $context['attempt'] === 1
            && str_contains((string) $context['error'], 'cURL error 28')
            && ! str_contains((string) $context['error'], 'bot-token'));
});

it('gives up after the last attempt', function (): void {
    telegramIsDown();
    Log::spy();

    $job = (new SendBoxActivityAlert($this->box->id, 'Spento o non connesso.', '777'))->withFakeQueueInteractions();
    $job->job->attempts = 3;
    $job->handle(resolve(JwOrgCatalog::class));

    $job->assertNotReleased();
    Log::shouldHaveReceived('error')
        ->once()
        ->withArgs(fn (string $message, array $context): bool => ! str_contains(json_encode($context), 'bot-token'));
});
