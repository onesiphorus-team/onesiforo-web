<?php

declare(strict_types=1);

use App\Enums\CommandType;
use App\Models\Command;
use App\Models\CustomCommand;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandService;

beforeEach(function (): void {
    $this->box = OnesiBox::factory()->online()->create();
    $this->commandService = resolve(OnesiBoxCommandService::class);
});

/**
 * @return list<string>
 */
function sentCommandTypes(OnesiBox $box): array
{
    return $box->commands()->orderBy('id')->get()->map(fn (Command $command): string => $command->type->value)->all();
}

it('sends only the content to a box without automatic commands', function (): void {
    CustomCommand::factory()->forBox($this->box)->create(['script_name' => 'to-box.sh']);

    $this->commandService->sendMediaCommand($this->box, 'https://www.jw.org/video', 'video');

    expect(sentCommandTypes($this->box))->toBe([CommandType::PlayMedia->value]);
});

it('runs the automatic commands in the background right before the content', function (): void {
    $toBox = CustomCommand::factory()->forBox($this->box)->runsBeforeContent()->create([
        'script_name' => 'to-box.sh',
        'static_args' => ['hdmi2'],
    ]);

    $this->commandService->sendMediaCommand($this->box, 'https://www.jw.org/video', 'video');

    expect(sentCommandTypes($this->box))->toBe([CommandType::CustomScript->value, CommandType::PlayMedia->value]);

    $script = $this->box->commands()->where('type', CommandType::CustomScript)->sole();
    expect($script->payload)->toBe([
        'custom_command_id' => $toBox->id,
        'script_name' => 'to-box.sh',
        'static_args' => ['hdmi2'],
        'run_in_background' => true,
        'automatic' => true,
    ])->and($script->priority)->toBeLessThan($this->box->commands()->where('type', CommandType::PlayMedia)->sole()->priority);
});

it('prepares the box when it joins a meeting too', function (): void {
    CustomCommand::factory()->forBox($this->box)->runsBeforeContent()->create(['script_name' => 'to-box.sh']);

    $this->commandService->sendZoomUrlCommand($this->box, 'https://us05web.zoom.us/j/123456789', 'Teresa');

    expect(sentCommandTypes($this->box))->toBe([CommandType::CustomScript->value, CommandType::JoinZoom->value]);
});

it('skips disabled automatic commands and those of other boxes', function (): void {
    CustomCommand::factory()->forBox($this->box)->runsBeforeContent()->disabled()->create(['script_name' => 'to-box.sh']);
    CustomCommand::factory()->runsBeforeContent()->create(['script_name' => 'to-box.sh']);

    $this->commandService->sendMediaCommand($this->box, 'https://www.jw.org/video', 'video');

    expect(sentCommandTypes($this->box))->toBe([CommandType::PlayMedia->value]);
});

it('does not repeat an automatic command sent less than a minute ago', function (): void {
    CustomCommand::factory()->forBox($this->box)->runsBeforeContent()->create(['script_name' => 'to-box.sh']);

    $this->commandService->sendMediaCommand($this->box, 'https://www.jw.org/video-1', 'video');
    $this->travel(30)->seconds();
    $this->commandService->sendMediaCommand($this->box, 'https://www.jw.org/video-2', 'video');

    expect($this->box->commands()->where('type', CommandType::CustomScript)->count())->toBe(1);

    $this->travel(61)->seconds();
    $this->box->update(['last_seen_at' => now()]);
    $this->commandService->sendMediaCommand($this->box, 'https://www.jw.org/video-3', 'video');

    expect($this->box->commands()->where('type', CommandType::CustomScript)->count())->toBe(2);
});

it('prepares the box only for the first item of a playlist', function (): void {
    CustomCommand::factory()->forBox($this->box)->runsBeforeContent()->create(['script_name' => 'to-box.sh']);

    $this->commandService->sendSessionMediaCommand($this->box, 'https://www.jw.org/video-1', 'video', 'session-uuid', startsSession: true);
    $this->travel(5)->minutes();
    $this->box->update(['last_seen_at' => now()]);
    $this->commandService->sendSessionMediaCommand($this->box, 'https://www.jw.org/video-2', 'video', 'session-uuid');

    expect(sentCommandTypes($this->box))->toBe([CommandType::CustomScript->value, CommandType::PlayMedia->value, CommandType::PlayMedia->value]);
});
