# Service Contracts

**Feature**: 004-caregiver-dashboard
**Date**: 2026-01-22

## OnesiBoxCommandService

**Purpose**: Gestisce l'invio di comandi alle appliance OnesiBox

### Interface

```php
namespace App\Services;

use App\Models\OnesiBox;

interface OnesiBoxCommandServiceInterface
{
    /**
     * Invia comando di riproduzione audio.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendAudioCommand(OnesiBox $onesiBox, string $audioUrl): void;

    /**
     * Invia comando di riproduzione video.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendVideoCommand(OnesiBox $onesiBox, string $videoUrl): void;

    /**
     * Invia comando di avvio chiamata Zoom.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendZoomCommand(OnesiBox $onesiBox, string $meetingId, ?string $password = null): void;

    /**
     * Invia comando di stop/terminazione.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendStopCommand(OnesiBox $onesiBox): void;
}
```

### Implementation

```php
namespace App\Services;

use App\Events\OnesiBoxCommandSent;
use App\Exceptions\OnesiBoxOfflineException;
use App\Jobs\SendOnesiBoxCommand;
use App\Models\OnesiBox;

class OnesiBoxCommandService implements OnesiBoxCommandServiceInterface
{
    public function sendAudioCommand(OnesiBox $onesiBox, string $audioUrl): void
    {
        $this->ensureOnline($onesiBox);

        SendOnesiBoxCommand::dispatch($onesiBox, 'audio', ['url' => $audioUrl]);

        OnesiBoxCommandSent::dispatch($onesiBox, auth()->user(), 'audio', ['url' => $audioUrl]);
    }

    public function sendVideoCommand(OnesiBox $onesiBox, string $videoUrl): void
    {
        $this->ensureOnline($onesiBox);

        SendOnesiBoxCommand::dispatch($onesiBox, 'video', ['url' => $videoUrl]);

        OnesiBoxCommandSent::dispatch($onesiBox, auth()->user(), 'video', ['url' => $videoUrl]);
    }

    public function sendZoomCommand(OnesiBox $onesiBox, string $meetingId, ?string $password = null): void
    {
        $this->ensureOnline($onesiBox);

        SendOnesiBoxCommand::dispatch($onesiBox, 'zoom', [
            'meeting_id' => $meetingId,
            'password' => $password,
        ]);

        OnesiBoxCommandSent::dispatch($onesiBox, auth()->user(), 'zoom', [
            'meeting_id' => $meetingId,
        ]);
    }

    public function sendStopCommand(OnesiBox $onesiBox): void
    {
        $this->ensureOnline($onesiBox);

        SendOnesiBoxCommand::dispatch($onesiBox, 'stop', []);

        OnesiBoxCommandSent::dispatch($onesiBox, auth()->user(), 'stop', []);
    }

    private function ensureOnline(OnesiBox $onesiBox): void
    {
        if (!$onesiBox->isOnline()) {
            throw new OnesiBoxOfflineException($onesiBox);
        }
    }
}
```

---

## SendOnesiBoxCommand Job

**Purpose**: Esegue l'invio effettivo del comando in background

### Job Class

```php
namespace App\Jobs;

use App\Models\OnesiBox;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SendOnesiBoxCommand implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;
    public int $backoff = 5;

    public function __construct(
        public OnesiBox $onesiBox,
        public string $commandType,
        public array $payload
    ) {}

    public function handle(): void
    {
        // TODO: Implementare comunicazione con appliance
        // Possibili approcci:
        // 1. HTTP API verso appliance
        // 2. MQTT publish
        // 3. WebSocket push via Reverb

        logger()->info('OnesiBox command sent', [
            'onesibox_id' => $this->onesiBox->id,
            'command' => $this->commandType,
            'payload' => $this->payload,
        ]);
    }

    public function failed(\Throwable $exception): void
    {
        logger()->error('OnesiBox command failed', [
            'onesibox_id' => $this->onesiBox->id,
            'command' => $this->commandType,
            'error' => $exception->getMessage(),
        ]);
    }
}
```

---

## Exceptions

### OnesiBoxOfflineException

```php
namespace App\Exceptions;

use App\Models\OnesiBox;
use Exception;

class OnesiBoxOfflineException extends Exception
{
    public function __construct(
        public OnesiBox $onesiBox
    ) {
        parent::__construct("OnesiBox {$onesiBox->name} is offline");
    }
}
```

### OnesiBoxCommandException

```php
namespace App\Exceptions;

use App\Models\OnesiBox;
use Exception;

class OnesiBoxCommandException extends Exception
{
    public function __construct(
        public OnesiBox $onesiBox,
        string $message,
        public string $commandType
    ) {
        parent::__construct($message);
    }
}
```

---

## Service Provider Registration

```php
// app/Providers/AppServiceProvider.php

public function register(): void
{
    $this->app->bind(
        OnesiBoxCommandServiceInterface::class,
        OnesiBoxCommandService::class
    );
}
```
