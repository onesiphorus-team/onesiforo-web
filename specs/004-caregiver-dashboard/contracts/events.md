# Broadcast Events Contract

**Feature**: 004-caregiver-dashboard
**Date**: 2026-01-22

## Event: OnesiBoxStatusUpdated

**Purpose**: Notifica ai caregiver quando lo stato di un'OnesiBox cambia

### Event Class

```php
namespace App\Events;

use App\Models\OnesiBox;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OnesiBoxStatusUpdated implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public OnesiBox $onesiBox
    ) {}

    public function broadcastOn(): array
    {
        return [
            new PrivateChannel("onesibox.{$this->onesiBox->id}"),
        ];
    }

    public function broadcastWith(): array
    {
        return [
            'id' => $this->onesiBox->id,
            'status' => $this->onesiBox->status?->value,
            'is_online' => $this->onesiBox->isOnline(),
            'last_seen_at' => $this->onesiBox->last_seen_at?->toISOString(),
        ];
    }

    public function broadcastAs(): string
    {
        return 'StatusUpdated';
    }
}
```

### Channel Authorization

```php
// routes/channels.php

use App\Models\OnesiBox;
use App\Models\User;

Broadcast::channel('onesibox.{id}', function (User $user, int $id) {
    $onesiBox = OnesiBox::find($id);

    return $onesiBox && $onesiBox->userCanView($user);
});
```

### Payload Schema

```json
{
  "id": 123,
  "status": "playing",
  "is_online": true,
  "last_seen_at": "2026-01-22T10:30:00.000Z"
}
```

### Livewire Listener

```php
// In OnesiBoxDetail.php

use Livewire\Attributes\On;

#[On('echo-private:onesibox.{onesiBox.id},StatusUpdated')]
public function refreshStatus(array $payload): void
{
    $this->onesiBox->refresh();
}
```

---

## Event: OnesiBoxCommandSent

**Purpose**: Conferma invio comando all'appliance (internal, non broadcast)

### Event Class

```php
namespace App\Events;

use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class OnesiBoxCommandSent
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public OnesiBox $onesiBox,
        public User $user,
        public string $commandType,
        public array $payload
    ) {}
}
```

### Usage

```php
// In OnesiBoxCommandService.php

OnesiBoxCommandSent::dispatch(
    $onesiBox,
    auth()->user(),
    'audio',
    ['url' => $audioUrl]
);
```

---

## Event Dispatch Points

| Trigger | Event | Dispatched By |
|---------|-------|---------------|
| Heartbeat API riceve status change | OnesiBoxStatusUpdated | HeartbeatController |
| Caregiver invia comando | OnesiBoxCommandSent | OnesiBoxCommandService |
| Appliance conferma esecuzione | OnesiBoxStatusUpdated | (via heartbeat) |

---

## Echo Client Configuration

```javascript
// resources/js/echo.js

import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

window.Pusher = Pusher;

window.Echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: import.meta.env.VITE_REVERB_PORT ?? 80,
    wssPort: import.meta.env.VITE_REVERB_PORT ?? 443,
    forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'https') === 'https',
    enabledTransports: ['ws', 'wss'],
});
```
