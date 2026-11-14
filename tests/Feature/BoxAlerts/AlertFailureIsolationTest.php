<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\OnesiBoxStatus;
use App\Enums\PlaybackSessionStatus;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\Playlist;
use App\Models\PlaylistItem;
use App\Services\BoxAlerts\BoxActivityMonitor;
use Illuminate\Support\Facades\Exceptions;

/**
 * Alerts are a side effect: whatever goes wrong while preparing one, the box must still get
 * its heartbeat, its acknowledgment and the next item of its playlist.
 */
beforeEach(function (): void {
    Exceptions::fake();
    $this->box = OnesiBox::factory()->online()->create(['last_seen_at' => now()->subMinutes(2)]);
    $this->headers = ['Authorization' => 'Bearer '.$this->box->createToken('onesibox-api-token')->plainTextToken];

    $monitor = Mockery::mock(BoxActivityMonitor::class);
    $monitor->shouldReceive('heartbeatReceived', 'customScriptFailed', 'playbackEventReceived', 'sessionEnded')
        ->andThrow(new RuntimeException('alerts are broken'));
    app()->instance(BoxActivityMonitor::class, $monitor);
});

it('still records the heartbeat', function (): void {
    $this->postJson(route('api.v1.appliances.heartbeat'), ['status' => OnesiBoxStatus::Idle->value], $this->headers)->assertOk();

    expect($this->box->refresh()->last_seen_at->diffInSeconds(now()))->toBeLessThan(5);
    Exceptions::assertReported(fn (RuntimeException $exception): bool => $exception->getMessage() === 'alerts are broken');
});

it('still acknowledges a failed custom script', function (): void {
    $command = Command::factory()->for($this->box, 'onesiBox')->pending()->create(['type' => CommandType::CustomScript, 'payload' => ['script_name' => 'to-box.sh']]);

    $this->postJson(route('api.v1.commands.ack', $command), [
        'status' => 'failed',
        'executed_at' => now()->toIso8601String(),
        'error_code' => 'E114',
        'error_message' => 'exited with 1',
    ], $this->headers)->assertOk();

    expect($command->refresh()->status)->toBe(CommandStatus::Failed);
    Exceptions::assertReported(RuntimeException::class);
});

it('still records the playback event and moves the session on', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->box)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create(['media_url' => 'https://www.jw.org/video/1']);
    $session = PlaybackSession::factory()->forOnesiBox($this->box)->forPlaylist($playlist)->create();

    $this->postJson(route('api.v1.appliances.playback'), [
        'event' => 'completed',
        'media_url' => 'https://www.jw.org/video/1',
        'media_type' => 'video',
        'session_id' => $session->uuid,
    ], $this->headers)->assertOk();

    expect($session->refresh()->status)->toBe(PlaybackSessionStatus::Completed)
        ->and($this->box->playbackEvents()->count())->toBe(1);
    Exceptions::assertReported(RuntimeException::class);
});

it('still expires on idle a session whose time is up', function (): void {
    $playlist = Playlist::factory()->forOnesiBox($this->box)->create();
    PlaylistItem::factory()->for($playlist)->atPosition(0)->create();
    $session = PlaybackSession::factory()->forOnesiBox($this->box)->forPlaylist($playlist)->active()->withDuration(30)->create(['started_at' => now()->subHour()]);

    $this->postJson(route('api.v1.appliances.heartbeat'), ['status' => OnesiBoxStatus::Idle->value], $this->headers)->assertOk();

    expect($session->refresh()->status)->toBe(PlaybackSessionStatus::Completed);
});
