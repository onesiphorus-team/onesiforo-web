<?php

declare(strict_types=1);

use App\Models\Congregation;
use App\Models\MeetingInstance;
use App\Models\OnesiBox;
use App\Models\User;
use App\Notifications\MeetingUpcomingNotification;
use Illuminate\Support\Facades\Notification;

it('sends notification via database and broadcast channels', function (): void {
    Notification::fake();

    $user = User::factory()->create();
    $congregation = Congregation::factory()->create(['name' => 'Roma Centro']);
    $instance = MeetingInstance::factory()->create(['congregation_id' => $congregation->id]);
    $box = OnesiBox::factory()->create();

    $notification = new MeetingUpcomingNotification($instance, $box);
    $user->notify($notification);

    Notification::assertSentTo($user, MeetingUpcomingNotification::class, fn ($n, $channels): bool => in_array('database', $channels) && in_array('broadcast', $channels));
});

it('includes telegram channel when user has telegram_chat_id', function (): void {
    Notification::fake();

    $user = User::factory()->create(['telegram_chat_id' => '123456']);
    $instance = MeetingInstance::factory()->create();
    $box = OnesiBox::factory()->create();

    $notification = new MeetingUpcomingNotification($instance, $box);
    $user->notify($notification);

    Notification::assertSentTo($user, MeetingUpcomingNotification::class, fn ($n, $channels): bool => in_array('telegram', $channels));
});

it('excludes telegram channel when user has no telegram_chat_id', function (): void {
    Notification::fake();

    $user = User::factory()->create(['telegram_chat_id' => null]);
    $instance = MeetingInstance::factory()->create();
    $box = OnesiBox::factory()->create();

    $notification = new MeetingUpcomingNotification($instance, $box);
    $user->notify($notification);

    Notification::assertSentTo($user, MeetingUpcomingNotification::class, fn ($n, $channels): bool => ! in_array('telegram', $channels));
});

it('has correct database notification content', function (): void {
    $congregation = Congregation::factory()->create(['name' => 'Roma Centro']);
    $instance = MeetingInstance::factory()->create([
        'congregation_id' => $congregation->id,
        'type' => 'midweek',
    ]);
    $box = OnesiBox::factory()->create(['name' => 'Box Maria']);

    $notification = new MeetingUpcomingNotification($instance, $box);
    $data = $notification->toArray(User::factory()->create());

    expect($data['meeting_instance_id'])->toBe($instance->id);
    expect($data['onesi_box_id'])->toBe($box->id);
    expect($data)->toHaveKey('message');
});
