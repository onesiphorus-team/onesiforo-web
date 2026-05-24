<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Jobs\SendOnesiBoxCommand;
use App\Models\Command;
use App\Models\CustomCommand;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandService;
use Illuminate\Support\Facades\Queue;

it('creates a CustomScript command with the expected payload', function (): void {
    Queue::fake();

    $box = OnesiBox::factory()->online()->create();
    $custom = CustomCommand::factory()->forBox($box)->create([
        'script_name' => 'to-box.sh',
        'static_args' => ['--mode', 'kiosk'],
    ]);

    (new OnesiBoxCommandService)->sendCustomScriptCommand($box, $custom);

    $command = Command::query()->where('onesi_box_id', $box->id)->latest('id')->first();

    expect($command)->not->toBeNull()
        ->and($command->type)->toBe(CommandType::CustomScript)
        ->and($command->status)->toBe(CommandStatus::Pending)
        ->and($command->payload['custom_command_id'])->toBe($custom->id)
        ->and($command->payload['script_name'])->toBe('to-box.sh')
        ->and($command->payload['static_args'])->toBe(['--mode', 'kiosk']);

    Queue::assertPushed(SendOnesiBoxCommand::class);
});

it('throws when the box is offline', function (): void {
    $box = OnesiBox::factory()->create(['last_seen_at' => now()->subHour()]);
    $custom = CustomCommand::factory()->forBox($box)->create();

    (new OnesiBoxCommandService)->sendCustomScriptCommand($box, $custom);
})->throws(App\Exceptions\OnesiBoxOfflineException::class);
