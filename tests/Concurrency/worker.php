<?php

declare(strict_types=1);

use App\Actions\DeleteUserAction;
use App\Actions\Sessions\AdvancePlaybackSessionAction;
use App\Actions\Sessions\StartPlaybackSessionAction;
use App\Actions\Sessions\StopPlaybackSessionAction;
use App\Enums\PlaybackEventType;
use App\Filament\Resources\Users\Pages\EditUser;
use App\Models\OnesiBox;
use App\Models\PlaybackSession;
use App\Models\Playlist;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Symfony\Component\HttpKernel\Exception\HttpException;

require dirname(__DIR__).'/concurrency-bootstrap.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
Queue::fake();

$operation = json_decode($argv[1], true, flags: JSON_THROW_ON_ERROR);
$actor = isset($operation['actor']) ? User::findOrFail($operation['actor'])->load('roles') : null;
if ($actor) {
    auth()->login($actor);
}
$target = isset($operation['target']) ? User::withTrashed()->findOrFail($operation['target'])->load('roles') : null;
$box = isset($operation['box']) ? OnesiBox::findOrFail($operation['box']) : null;
$session = isset($operation['session']) ? PlaybackSession::findOrFail($operation['session']) : null;
$playlist = isset($operation['playlist']) ? Playlist::findOrFail($operation['playlist']) : null;

fwrite(STDOUT, json_encode(['ready' => DB::selectOne('SELECT CONNECTION_ID() AS id')->id])."\n");
fflush(STDOUT);
fgets(STDIN);

$held = false;
DB::listen(function (QueryExecuted $query) use ($operation, &$held): void {
    if (($operation['hold'] ?? false) && ! $held && str_contains(strtolower($query->sql), 'for update')) {
        $held = true;
        fwrite(STDOUT, "locked\n");
        fflush(STDOUT);
        fgets(STDIN);
    }
});

try {
    switch ($operation['action']) {
        case 'delete':
            $result = app(DeleteUserAction::class)($target, $operation['force'] ?? false);
            break;
        case 'edit':
            $page = new EditUser;
            (new ReflectionProperty(EditUser::class, 'roles'))->setValue($page, $operation['roles'] ?? null);
            (new ReflectionMethod(EditUser::class, 'handleRecordUpdate'))->invoke($page, $target, ['name' => $operation['name'] ?? $target->name]);
            $result = true;
            break;
        case 'start':
            $result = app(StartPlaybackSessionAction::class)->execute($box, $playlist, 30)->id;
            break;
        case 'stop':
            $result = app(StopPlaybackSessionAction::class)->execute($session)->status->value;
            break;
        case 'advance':
            app(AdvancePlaybackSessionAction::class)->execute($box, PlaybackEventType::Completed, $operation['url'], $operation['uuid']);
            $result = true;
            break;
        default:
            throw new RuntimeException('Unknown concurrency operation.');
    }
} catch (AuthorizationException $exception) {
    $result = false;
} catch (HttpException $exception) {
    if ($exception->getStatusCode() !== 403) {
        throw $exception;
    }
    $result = false;
}
fwrite(STDOUT, json_encode(['result' => $result])."\n");
