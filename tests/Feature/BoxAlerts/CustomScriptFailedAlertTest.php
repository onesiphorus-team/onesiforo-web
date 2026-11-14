<?php

declare(strict_types=1);

use App\Enums\CommandType;
use App\Models\Command;
use App\Models\CustomCommand;
use App\Models\OnesiBox;
use App\Notifications\BoxActivityNotification;
use App\Services\OnesiBoxCommandService;
use Illuminate\Support\Facades\Notification;

beforeEach(function (): void {
    Notification::fake();
    config(['services.telegram-bot-api.support_chat_id' => '-1001234567890']);
    $this->box = OnesiBox::factory()->online()->create();
    $this->token = $this->box->createToken('onesibox-api-token')->plainTextToken;
    $this->toBox = CustomCommand::factory()->forBox($this->box)->runsBeforeContent()->create(['name' => 'TV sul box', 'script_name' => 'to-box.sh']);
});

function reportResult(Command $command, string $status, ?string $errorCode = null, ?string $errorMessage = null): void
{
    test()->postJson(route('api.v1.commands.ack', $command), array_filter([
        'status' => $status,
        'executed_at' => now()->toIso8601String(),
        'error_code' => $errorCode,
        'error_message' => $errorMessage,
    ]), ['Authorization' => 'Bearer '.test()->token])->assertOk();
}

function automaticToBoxCommand(): Command
{
    resolve(OnesiBoxCommandService::class)->sendMediaCommand(test()->box, 'https://www.jw.org/video', 'video');

    return test()->box->commands()->where('type', CommandType::CustomScript)->sole();
}

/**
 * @return list<string>
 */
function scriptAlerts(): array
{
    $alerts = [];
    Notification::assertSentOnDemand(BoxActivityNotification::class, function (BoxActivityNotification $notification) use (&$alerts): bool {
        $alerts[] = $notification->html;

        return true;
    });

    return $alerts;
}

it('warns that the content may not be visible when the automatic to-box fails, with the reason', function (): void {
    reportResult(automaticToBoxCommand(), 'failed', 'E114', 'script to-box.sh exited with 1: [to-box] TV non raggiungibile');

    expect(scriptAlerts())->toHaveCount(1)
        ->and(scriptAlerts()[0])->toContain(
            'Il comando automatico «TV sul box» (to-box.sh) non è riuscito',
            'potrebbe non vedersi sulla TV',
            'Motivo: script to-box.sh exited with 1: [to-box] TV non raggiungibile',
        );
});

it('explains timeouts and missing scripts in plain words', function (string $errorCode, string $explanation): void {
    reportResult(automaticToBoxCommand(), 'failed', $errorCode, 'technical message');

    expect(scriptAlerts()[0])->toContain($explanation);
})->with([
    'timeout' => ['E010', 'non ha risposto entro 30 secondi'],
    'not installed' => ['E115', 'non è installato sul box'],
]);

it('also reports a custom command started by hand that failed', function (): void {
    resolve(OnesiBoxCommandService::class)->sendCustomScriptCommand($this->box, $this->toBox);

    reportResult($this->box->commands()->sole(), 'failed', 'E114', 'exited with 1');

    expect(scriptAlerts()[0])->toContain('⚠️ Il comando «TV sul box» (to-box.sh) non è riuscito')->not->toContain('automatico');
});

it('stays quiet when the script works, and reports repeated failures once in ten minutes', function (): void {
    reportResult(automaticToBoxCommand(), 'success');
    Notification::assertNothingSent();

    $failing = Command::factory()->for($this->box)->pending()->create([
        'type' => CommandType::CustomScript,
        'payload' => ['custom_command_id' => $this->toBox->id, 'script_name' => 'to-box.sh', 'automatic' => true],
    ]);
    $failingAgain = Command::factory()->for($this->box)->pending()->create([
        'type' => CommandType::CustomScript,
        'payload' => ['custom_command_id' => $this->toBox->id, 'script_name' => 'to-box.sh', 'automatic' => true],
    ]);
    reportResult($failing, 'failed', 'E114', 'exited with 1');
    reportResult($failingAgain, 'failed', 'E114', 'exited with 1');

    expect(scriptAlerts())->toHaveCount(1);
});

it('does not report failures of other kinds of commands', function (): void {
    $command = Command::factory()->for($this->box)->pending()->create(['type' => CommandType::PlayMedia, 'payload' => ['url' => 'https://www.jw.org/video', 'media_type' => 'video']]);

    reportResult($command, 'failed', 'E110', 'Nessun media');

    Notification::assertNothingSent();
});
