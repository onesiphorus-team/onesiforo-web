# Backend `play_stream_item` Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Aggiungere al backend Onesiforo la capacità di emettere il comando `play_stream_item` verso un OnesiBox tramite nuova UI Livewire `StreamPlayer`, più due fix di plumbing condiviso (`error_code` nelle playback events + broadcast reattivo).

**Architecture:** 12 task divisi in shared plumbing (3), backend logic (3), UI Livewire (5), docs (1). Nuovo component Livewire `StreamPlayer` con `Precedente`/`Successivo`/`Stop`. Self-limiting (reazione a E112 via WebSocket broadcast). Stato ricostruito da tabella `commands` al mount.

**Tech Stack:** Laravel 11+, Livewire 3, Flux UI, Reverb (WebSocket), Pest 3, Filament Enum contracts.

**Spec:** `docs/superpowers/specs/2026-04-22-play-stream-item-backend-design.md`

---

## File structure

### Shared plumbing (prerequisite per UI reattiva)

- **Create**: `database/migrations/YYYY_MM_DD_HHMMSS_add_error_code_to_playback_events_table.php`
- **Modify**: `app/Models/PlaybackEvent.php` (aggiunge `error_code` a `$fillable`, property docblock)
- **Modify**: `app/Http/Requests/Api/V1/PlaybackEventRequest.php` (validation rule + messaggio italiano + attribute)
- **Modify**: `app/Actions/StorePlaybackEventAction.php` (param `errorCode` in `__invoke` + `fromArray`)
- **Create**: `app/Events/PlaybackEventReceived.php` (broadcast event)
- **Modify**: `app/Http/Controllers/Api/V1/PlaybackController.php` (chiama `broadcast()`)
- **Test** (unit/feature): vari (vedi task specifici)

### Backend logic (comando e validazione)

- **Modify**: `app/Enums/CommandType.php` (+ case `PlayStreamItem`)
- **Create**: `app/Rules/JwStreamUrl.php` + `tests/Unit/Rules/JwStreamUrlTest.php`
- **Modify**: `app/Services/OnesiBoxCommandService.php` (+ method `sendStreamItemCommand`)
- **Modify**: `app/Services/OnesiBoxCommandServiceInterface.php` (+ firma)
- **Test**: `tests/Feature/Services/OnesiBoxCommandServiceTest.php` (se non esiste si crea)

### UI Livewire

- **Create**: `app/Livewire/Dashboard/Controls/StreamPlayer.php` (~150 LOC)
- **Create**: `resources/views/livewire/dashboard/controls/stream-player.blade.php`
- **Modify**: `resources/views/livewire/dashboard/onesi-box-detail.blade.php` (+ 1 riga)
- **Test**: `tests/Feature/Livewire/StreamPlayerTest.php`

---

## Task 1: Migration + modello per `error_code` in `playback_events`

**Files:**
- Create: `database/migrations/<timestamp>_add_error_code_to_playback_events_table.php`
- Modify: `app/Models/PlaybackEvent.php`

- [ ] **Step 1: Generare la migration**

Run: `php artisan make:migration add_error_code_to_playback_events_table --table=playback_events`

Verifica che venga creato un file `database/migrations/<timestamp>_add_error_code_to_playback_events_table.php`.

- [ ] **Step 2: Scrivere il contenuto della migration**

Apri il file generato e sostituisci il contenuto con:

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('playback_events', function (Blueprint $table) {
            $table->string('error_code', 10)->nullable()->after('error_message');
            $table->index('error_code');
        });
    }

    public function down(): void
    {
        Schema::table('playback_events', function (Blueprint $table) {
            $table->dropIndex(['error_code']);
            $table->dropColumn('error_code');
        });
    }
};
```

- [ ] **Step 3: Aggiornare `app/Models/PlaybackEvent.php`**

Aggiungere `'error_code'` a `$fillable` (dopo `'error_message'`), e aggiungere la property nel docblock:

```php
 * @property string|null $error_message
 * @property string|null $error_code
 * @property string|null $session_id
```

```php
protected $fillable = [
    'onesi_box_id',
    'event',
    'media_url',
    'media_type',
    'position',
    'duration',
    'error_message',
    'error_code',
    'session_id',
    'created_at',
];
```

- [ ] **Step 4: Eseguire la migration**

Run: `php artisan migrate`
Expected: `INFO  Running migrations. ... add_error_code_to_playback_events_table ....................... DONE`

- [ ] **Step 5: Commit**

```bash
git add database/migrations/ app/Models/PlaybackEvent.php
git commit -m "feat(playback-events): add error_code column for granular error tracking"
```

---

## Task 2: `PlaybackEventRequest` e `StorePlaybackEventAction` accettano `error_code`

**Files:**
- Modify: `app/Http/Requests/Api/V1/PlaybackEventRequest.php`
- Modify: `app/Actions/StorePlaybackEventAction.php`
- Test: `tests/Unit/Actions/StorePlaybackEventActionTest.php` (se non esiste si crea)

- [ ] **Step 1: Scrivere i test falliti per `StorePlaybackEventAction`**

Se il file `tests/Unit/Actions/StorePlaybackEventActionTest.php` non esiste, crearlo. Altrimenti aprire il file esistente e AGGIUNGERE (non sostituire) questi test Pest:

```php
<?php

declare(strict_types=1);

use App\Actions\StorePlaybackEventAction;
use App\Enums\PlaybackEventType;
use App\Models\OnesiBox;

it('persists error_code when provided', function () {
    $box = OnesiBox::factory()->create();

    $event = (new StorePlaybackEventAction())(
        onesiBox: $box,
        event: PlaybackEventType::Error,
        mediaUrl: 'https://stream.jw.org/6311-4713-5379-2156',
        mediaType: 'video',
        errorMessage: 'Ordinal 99 exceeds playlist length 4',
        errorCode: 'E112',
    );

    expect($event->error_code)->toBe('E112')
        ->and($event->error_message)->toBe('Ordinal 99 exceeds playlist length 4');
});

it('persists null error_code when not provided', function () {
    $box = OnesiBox::factory()->create();

    $event = (new StorePlaybackEventAction())(
        onesiBox: $box,
        event: PlaybackEventType::Started,
        mediaUrl: 'https://www.jw.org/...',
        mediaType: 'video',
    );

    expect($event->error_code)->toBeNull();
});

it('fromArray extracts error_code from payload', function () {
    $box = OnesiBox::factory()->create();

    $event = (new StorePlaybackEventAction())->fromArray($box, [
        'event' => 'error',
        'media_url' => 'https://stream.jw.org/x',
        'media_type' => 'video',
        'error_code' => 'E111',
        'error_message' => 'No tiles found',
    ]);

    expect($event->error_code)->toBe('E111');
});
```

- [ ] **Step 2: Eseguire per confermare il fallimento**

Run: `vendor/bin/pest tests/Unit/Actions/StorePlaybackEventActionTest.php`
Expected: FAIL — `$event->error_code` sarà undefined / errore metodo.

- [ ] **Step 3: Aggiornare `app/Actions/StorePlaybackEventAction.php`**

Aggiungere parametro `errorCode` a `__invoke()` e includerlo nel create. Aggiornare anche `fromArray()`:

```php
public function __invoke(
    OnesiBox $onesiBox,
    PlaybackEventType|string $event,
    string $mediaUrl,
    string $mediaType,
    ?int $position = null,
    ?int $duration = null,
    ?string $errorMessage = null,
    ?string $sessionId = null,
    ?string $errorCode = null,
): PlaybackEvent {
    $eventType = $event instanceof PlaybackEventType
        ? $event
        : PlaybackEventType::from($event);

    return PlaybackEvent::query()->create([
        'onesi_box_id' => $onesiBox->id,
        'event' => $eventType,
        'media_url' => $mediaUrl,
        'media_type' => $mediaType,
        'position' => $position,
        'duration' => $duration,
        'error_message' => $errorMessage,
        'error_code' => $errorCode,
        'session_id' => $sessionId,
    ]);
}
```

Aggiornare il docblock `@param array{...}` di `fromArray()` aggiungendo `error_code?: string|null`, e il corpo:

```php
public function fromArray(OnesiBox $onesiBox, array $data): PlaybackEvent
{
    return ($this)(
        onesiBox: $onesiBox,
        event: $data['event'],
        mediaUrl: $data['media_url'],
        mediaType: $data['media_type'],
        position: $data['position'] ?? null,
        duration: $data['duration'] ?? null,
        errorMessage: $data['error_message'] ?? null,
        sessionId: $data['session_id'] ?? null,
        errorCode: $data['error_code'] ?? null,
    );
}
```

E aggiornare docblock `/** @param array{...} */` per includere `error_code?: string|null`.

- [ ] **Step 4: Aggiornare `app/Http/Requests/Api/V1/PlaybackEventRequest.php`**

Aggiungere nella `rules()` (dopo `error_message`):

```php
'error_code' => [
    'nullable',
    'string',
    'regex:/^E\d{3}$/',
    'max:10',
],
```

Aggiungere in `messages()`:

```php
'error_code.regex' => 'Il codice errore deve essere nel formato E### (es. E110, E112).',
'error_code.max' => 'Il codice errore non può superare 10 caratteri.',
```

Aggiungere in `attributes()`:

```php
'error_code' => 'codice errore',
```

E aggiornare il docblock della classe per includere:

```php
 * @property-read string|null $error_code
```

- [ ] **Step 5: Eseguire i test**

Run: `vendor/bin/pest tests/Unit/Actions/StorePlaybackEventActionTest.php`
Expected: PASS 3/3.

- [ ] **Step 6: Commit**

```bash
git add app/Actions/StorePlaybackEventAction.php app/Http/Requests/Api/V1/PlaybackEventRequest.php tests/Unit/Actions/StorePlaybackEventActionTest.php
git commit -m "feat(playback-events): accept and persist error_code via API"
```

---

## Task 3: Evento broadcast `PlaybackEventReceived` + dispatch dal controller

**Files:**
- Create: `app/Events/PlaybackEventReceived.php`
- Modify: `app/Http/Controllers/Api/V1/PlaybackController.php`
- Test: `tests/Feature/Api/V1/PlaybackControllerTest.php` (se non esiste si crea con almeno i test nuovi)

- [ ] **Step 1: Scrivere il test fallito (broadcast)**

Se il file `tests/Feature/Api/V1/PlaybackControllerTest.php` non esiste, crearlo. Aggiungere:

```php
<?php

declare(strict_types=1);

use App\Events\PlaybackEventReceived;
use App\Models\OnesiBox;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;

it('broadcasts PlaybackEventReceived when a playback event is stored', function () {
    Event::fake([PlaybackEventReceived::class]);

    $box = OnesiBox::factory()->create();
    Sanctum::actingAs($box, ['*'], 'onesibox');

    $response = $this->postJson('/api/v1/appliances/playback', [
        'event' => 'error',
        'media_url' => 'https://stream.jw.org/6311-4713-5379-2156',
        'media_type' => 'video',
        'error_code' => 'E112',
        'error_message' => 'Ordinal 99 exceeds playlist length 4',
    ]);

    $response->assertOk();

    Event::assertDispatched(PlaybackEventReceived::class, function ($event) use ($box) {
        return $event->playbackEvent->onesi_box_id === $box->id
            && $event->playbackEvent->error_code === 'E112';
    });
});

it('accepts valid error_code values', function () {
    $box = OnesiBox::factory()->create();
    Sanctum::actingAs($box, ['*'], 'onesibox');

    $response = $this->postJson('/api/v1/appliances/playback', [
        'event' => 'error',
        'media_url' => 'https://stream.jw.org/x',
        'media_type' => 'video',
        'error_code' => 'E110',
        'error_message' => 'DNS timeout',
    ]);

    $response->assertOk();
});

it('rejects malformed error_code', function () {
    $box = OnesiBox::factory()->create();
    Sanctum::actingAs($box, ['*'], 'onesibox');

    $response = $this->postJson('/api/v1/appliances/playback', [
        'event' => 'error',
        'media_url' => 'https://stream.jw.org/x',
        'media_type' => 'video',
        'error_code' => 'oops',
        'error_message' => 'bad',
    ]);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors(['error_code']);
});
```

**Attenzione** alla guardia Sanctum: verifica in `config/sanctum.php` o nei middleware API come viene autenticato un OnesiBox. Se nel repo il pattern standard è diverso, adatta `Sanctum::actingAs(...)` di conseguenza — guarda un test esistente in `tests/Feature/Api/V1/` (es. test controller esistente con auth OnesiBox) per copiare il pattern.

- [ ] **Step 2: Eseguire i test — dovrebbero fallire**

Run: `vendor/bin/pest tests/Feature/Api/V1/PlaybackControllerTest.php`
Expected: FAIL — `PlaybackEventReceived` class non esiste.

- [ ] **Step 3: Creare `app/Events/PlaybackEventReceived.php`**

```php
<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\PlaybackEvent;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Broadcast quando un OnesiBox riporta un evento di playback.
 *
 * Permette a Livewire components (es. StreamPlayer) di reagire in tempo reale
 * a eventi started/paused/stopped/completed/error senza polling.
 */
class PlaybackEventReceived implements ShouldBroadcastNow
{
    use Dispatchable;
    use InteractsWithSockets;
    use SerializesModels;

    public function __construct(public PlaybackEvent $playbackEvent)
    {
    }

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel("appliance.{$this->playbackEvent->onesiBox->serial_number}");
    }

    public function broadcastAs(): string
    {
        return 'playback.event-received';
    }

    /**
     * @return array<string, mixed>
     */
    public function broadcastWith(): array
    {
        return [
            'event' => $this->playbackEvent->event->value,
            'media_url' => $this->playbackEvent->media_url,
            'media_type' => $this->playbackEvent->media_type,
            'error_code' => $this->playbackEvent->error_code,
            'error_message' => $this->playbackEvent->error_message,
            'occurred_at' => $this->playbackEvent->created_at->toISOString(),
        ];
    }
}
```

- [ ] **Step 4: Aggiornare `app/Http/Controllers/Api/V1/PlaybackController.php`**

Aggiungere l'import:

```php
use App\Events\PlaybackEventReceived;
```

Nel metodo `store()`, dopo `$playbackEvent = $storeAction->fromArray($onesiBox, $data);` e PRIMA del blocco `if ($eventType === ...)`, aggiungere:

```php
broadcast(new PlaybackEventReceived($playbackEvent));
```

Il metodo finale `store` deve assomigliare a:

```php
public function store(
    PlaybackEventRequest $request,
    StorePlaybackEventAction $storeAction,
    AdvancePlaybackSessionAction $advanceAction,
): PlaybackEventResource {
    $onesiBox = $request->onesiBox();

    /** @var array{event: string, media_url: string, media_type: string, position?: int|null, duration?: int|null, error_message?: string|null, error_code?: string|null, session_id?: string|null} $data */
    $data = $request->validated();
    $playbackEvent = $storeAction->fromArray($onesiBox, $data);

    broadcast(new PlaybackEventReceived($playbackEvent));

    $eventType = $playbackEvent->event;

    if ($eventType === PlaybackEventType::Completed || $eventType === PlaybackEventType::Error) {
        $advanceAction->execute($onesiBox, $eventType, $data['media_url']);
    }

    return new PlaybackEventResource([
        'logged' => true,
        'event_id' => $playbackEvent->id,
    ]);
}
```

(Nota: è stato aggiornato anche il docblock del data array per includere `error_code?: string|null`.)

- [ ] **Step 5: Eseguire i test**

Run: `vendor/bin/pest tests/Feature/Api/V1/PlaybackControllerTest.php`
Expected: PASS 3/3.

- [ ] **Step 6: Commit**

```bash
git add app/Events/PlaybackEventReceived.php app/Http/Controllers/Api/V1/PlaybackController.php tests/Feature/Api/V1/PlaybackControllerTest.php
git commit -m "feat(playback-events): broadcast PlaybackEventReceived for reactive UI"
```

---

## Task 4: `CommandType::PlayStreamItem` enum case

**Files:**
- Modify: `app/Enums/CommandType.php`

- [ ] **Step 1: Aggiungere il case e aggiornare i match**

Nel file `app/Enums/CommandType.php`:

**1a.** Aggiungere il case subito dopo `case PlayMedia = 'play_media';` (nella sezione `// Media`):

```php
case PlayStreamItem = 'play_stream_item';
```

**1b.** In `getLabel()`, aggiungere il nuovo caso dopo `self::PlayMedia => __('Riproduci media'),`:

```php
self::PlayStreamItem => __('Riproduci playlist JW Stream'),
```

**1c.** In `getIcon()`, aggiungere dopo `self::PlayMedia => 'heroicon-o-play',`:

```php
self::PlayStreamItem => 'heroicon-o-queue-list',
```

**1d.** In `getColor()`, aggiungere in coda al primo gruppo `success`:

```php
self::PlayMedia, self::ResumeMedia, self::PlayStreamItem => 'success',
```

**1e.** Non serve modificare `defaultExpiresInMinutes()` — il case `default => 60` copre già `PlayStreamItem`.

- [ ] **Step 2: Verificare che PHP non lanci errori**

Run: `php -l app/Enums/CommandType.php`
Expected: `No syntax errors detected`

Run: `vendor/bin/pest --filter=CommandType` (se esistono test esistenti) oppure `vendor/bin/pest tests/Unit/ tests/Feature/`
Expected: tutti i test passano, nessuna regressione.

- [ ] **Step 3: Commit**

```bash
git add app/Enums/CommandType.php
git commit -m "feat(commands): add PlayStreamItem case to CommandType enum"
```

---

## Task 5: Rule `JwStreamUrl` + unit test

**Files:**
- Create: `app/Rules/JwStreamUrl.php`
- Test: `tests/Unit/Rules/JwStreamUrlTest.php`

- [ ] **Step 1: Scrivere i test falliti**

Creare `tests/Unit/Rules/JwStreamUrlTest.php`:

```php
<?php

declare(strict_types=1);

use App\Rules\JwStreamUrl;

function validateJwStreamUrl(mixed $value): ?string
{
    $rule = new JwStreamUrl();
    $error = null;
    $rule->validate('url', $value, function ($message) use (&$error) {
        $error = (string) $message;
    });
    return $error;
}

it('accepts a stream.jw.org share link', function () {
    expect(validateJwStreamUrl('https://stream.jw.org/6311-4713-5379-2156'))->toBeNull();
});

it('accepts stream.jw.org /home path', function () {
    expect(validateJwStreamUrl('https://stream.jw.org/home'))->toBeNull();
});

it('accepts stream.jw.org /home?playerOpen=true', function () {
    expect(validateJwStreamUrl('https://stream.jw.org/home?playerOpen=true'))->toBeNull();
});

it('rejects http (no HTTPS)', function () {
    expect(validateJwStreamUrl('http://stream.jw.org/x'))->not->toBeNull();
});

it('rejects subdomain-injection attack', function () {
    expect(validateJwStreamUrl('https://stream.jw.org.evil.com/x'))->not->toBeNull();
});

it('rejects fake-stream subdomain', function () {
    expect(validateJwStreamUrl('https://fake-stream.jw.org/x'))->not->toBeNull();
});

it('rejects www.jw.org (wrong domain for this rule)', function () {
    expect(validateJwStreamUrl('https://www.jw.org/mediaitems/x'))->not->toBeNull();
});

it('rejects empty string', function () {
    expect(validateJwStreamUrl(''))->not->toBeNull();
});

it('rejects null', function () {
    expect(validateJwStreamUrl(null))->not->toBeNull();
});

it('rejects non-standard port', function () {
    expect(validateJwStreamUrl('https://stream.jw.org:9999/x'))->not->toBeNull();
});

it('rejects URL longer than 2048 characters', function () {
    $longPath = str_repeat('a', 3000);
    expect(validateJwStreamUrl("https://stream.jw.org/{$longPath}"))->not->toBeNull();
});
```

- [ ] **Step 2: Eseguire per confermare il fallimento**

Run: `vendor/bin/pest tests/Unit/Rules/JwStreamUrlTest.php`
Expected: FAIL — `Class App\Rules\JwStreamUrl not found`.

- [ ] **Step 3: Creare `app/Rules/JwStreamUrl.php`**

```php
<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Validates that a URL is a stream.jw.org URL.
 *
 * Accepted formats:
 * - https://stream.jw.org/NNNN-NNNN-NNNN-NNNN (share token)
 * - https://stream.jw.org/home (post-redirect)
 * - https://stream.jw.org/home?playerOpen=true
 */
class JwStreamUrl implements ValidationRule
{
    private const int MAX_URL_LENGTH = 2048;

    private const string VALID_HOST = 'stream.jw.org';

    /**
     * @param  Closure(string, ?string=): \Illuminate\Translation\PotentiallyTranslatedString  $fail
     */
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || $value === '') {
            $fail('L\'URL è obbligatorio.');

            return;
        }

        if (strlen($value) > self::MAX_URL_LENGTH) {
            $fail('L\'URL non può superare 2048 caratteri.');

            return;
        }

        $parsed = parse_url($value);

        if ($parsed === false || ! isset($parsed['scheme'], $parsed['host'])) {
            $fail('L\'URL non è valido.');

            return;
        }

        if ($parsed['scheme'] !== 'https') {
            $fail('L\'URL deve utilizzare HTTPS.');

            return;
        }

        if (isset($parsed['port']) && $parsed['port'] !== 443) {
            $fail('L\'URL non può usare porte non standard.');

            return;
        }

        $host = strtolower($parsed['host']);

        if ($host !== self::VALID_HOST) {
            $fail('L\'URL deve essere un link di stream.jw.org.');

            return;
        }
    }
}
```

- [ ] **Step 4: Eseguire i test**

Run: `vendor/bin/pest tests/Unit/Rules/JwStreamUrlTest.php`
Expected: PASS 11/11.

- [ ] **Step 5: Commit**

```bash
git add app/Rules/JwStreamUrl.php tests/Unit/Rules/JwStreamUrlTest.php
git commit -m "feat(rules): add JwStreamUrl validation rule for stream.jw.org URLs"
```

---

## Task 6: `OnesiBoxCommandService::sendStreamItemCommand` + interfaccia

**Files:**
- Modify: `app/Services/OnesiBoxCommandServiceInterface.php`
- Modify: `app/Services/OnesiBoxCommandService.php`
- Test: `tests/Feature/Services/OnesiBoxCommandServiceTest.php` (se non esiste, si crea)

- [ ] **Step 1: Scrivere i test falliti**

Se `tests/Feature/Services/OnesiBoxCommandServiceTest.php` non esiste, crearlo. In ogni caso, aggiungere:

```php
<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Events\OnesiBoxCommandSent;
use App\Exceptions\OnesiBoxOfflineException;
use App\Jobs\SendOnesiBoxCommand;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\User;
use App\Services\OnesiBoxCommandService;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;

it('sendStreamItemCommand creates a command with correct payload and priority', function () {
    Queue::fake();
    Event::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    // Assumo che OnesiBox::factory()->create() crei un box online.
    // Se il factory di default crea offline, usare ->online() o state corrispondente.
    $box = OnesiBox::factory()->create();
    // Forzare isOnline() a true (se serve - il metodo di factory varia; uno degli approcci è mettere $box last_heartbeat a now())
    $box->update(['last_heartbeat_at' => now()]);

    $service = new OnesiBoxCommandService();
    $service->sendStreamItemCommand($box, 'https://stream.jw.org/6311-4713-5379-2156', 2);

    $command = Command::query()->latest()->first();
    expect($command)->not->toBeNull()
        ->and($command->type)->toBe(CommandType::PlayStreamItem)
        ->and($command->payload)->toBe(['url' => 'https://stream.jw.org/6311-4713-5379-2156', 'ordinal' => 2])
        ->and($command->priority)->toBe(2)
        ->and($command->status)->toBe(CommandStatus::Pending)
        ->and($command->onesi_box_id)->toBe($box->id);

    Queue::assertPushed(SendOnesiBoxCommand::class);
    Event::assertDispatched(OnesiBoxCommandSent::class);
});

it('sendStreamItemCommand throws if box is offline', function () {
    Queue::fake();

    $user = User::factory()->create();
    $this->actingAs($user);

    $box = OnesiBox::factory()->create();
    $box->update(['last_heartbeat_at' => now()->subHours(1)]);

    $service = new OnesiBoxCommandService();
    $service->sendStreamItemCommand($box, 'https://stream.jw.org/x', 1);
})->throws(OnesiBoxOfflineException::class);
```

**Nota**: il comportamento di `isOnline()` dipende dal modello. Il test presuppone che `last_heartbeat_at` entro un certo threshold (es. 2 minuti) sia "online" — verifica il metodo `isOnline()` nel modello `OnesiBox` e adatta i timestamp di conseguenza. Se il factory di default produce un box online, puoi semplificare il primo test.

- [ ] **Step 2: Eseguire per confermare il fallimento**

Run: `vendor/bin/pest tests/Feature/Services/OnesiBoxCommandServiceTest.php`
Expected: FAIL — `method sendStreamItemCommand not found`.

- [ ] **Step 3: Aggiornare `app/Services/OnesiBoxCommandServiceInterface.php`**

Aggiungere in coda (prima della parentesi di chiusura `}`):

```php
    /**
     * Invia comando di riproduzione di un item specifico di una playlist JW Stream.
     *
     * @param  int  $ordinal  Ordinale del video nella playlist (1-indexed, 1-50)
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendStreamItemCommand(OnesiBox $onesiBox, string $url, int $ordinal): void;
```

- [ ] **Step 4: Aggiornare `app/Services/OnesiBoxCommandService.php`**

Aggiungere il metodo prima di `private function sendCommand(...)`, seguendo il pattern di `sendSessionMediaCommand`:

```php
    public function sendStreamItemCommand(OnesiBox $onesiBox, string $url, int $ordinal): void
    {
        $this->sendCommand($onesiBox, CommandType::PlayStreamItem, [
            'url' => $url,
            'ordinal' => $ordinal,
        ], priority: 2);
    }
```

- [ ] **Step 5: Eseguire i test**

Run: `vendor/bin/pest tests/Feature/Services/OnesiBoxCommandServiceTest.php --filter=sendStreamItemCommand`
Expected: PASS 2/2.

- [ ] **Step 6: Lint (Pint)**

Run: `vendor/bin/pint`
Expected: pulito (i file modificati risultano "already clean" o vengono formattati auto).

- [ ] **Step 7: Commit**

```bash
git add app/Services/OnesiBoxCommandServiceInterface.php app/Services/OnesiBoxCommandService.php tests/Feature/Services/OnesiBoxCommandServiceTest.php
git commit -m "feat(commands): add sendStreamItemCommand to OnesiBoxCommandService"
```

---

## Task 7: `StreamPlayer` Livewire component — skeleton + mount state restoration

**Files:**
- Create: `app/Livewire/Dashboard/Controls/StreamPlayer.php`
- Test: `tests/Feature/Livewire/StreamPlayerTest.php`

- [ ] **Step 1: Scrivere i primi test (mount/state restoration)**

Creare `tests/Feature/Livewire/StreamPlayerTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\CommandStatus;
use App\Enums\CommandType;
use App\Enums\PlaybackEventType;
use App\Livewire\Dashboard\Controls\StreamPlayer;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
use App\Models\User;
use Livewire\Livewire;

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
    $this->box = OnesiBox::factory()->create();
});

it('mounts with clean state when no recent stream item commands', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->assertSet('url', '')
        ->assertSet('lastOrdinalSent', null)
        ->assertSet('errorCode', null)
        ->assertSet('reachedEnd', false);
});

it('restores url and lastOrdinalSent from latest play_stream_item command in last 6 hours', function () {
    Command::query()->create([
        'onesi_box_id' => $this->box->id,
        'type' => CommandType::PlayStreamItem,
        'payload' => ['url' => 'https://stream.jw.org/6311-4713-5379-2156', 'ordinal' => 3],
        'priority' => 2,
        'status' => CommandStatus::Pending,
        'created_at' => now()->subHour(),
    ]);

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->assertSet('url', 'https://stream.jw.org/6311-4713-5379-2156')
        ->assertSet('lastOrdinalSent', 3)
        ->assertSet('errorCode', null)
        ->assertSet('reachedEnd', false);
});

it('ignores stream item commands older than 6 hours', function () {
    Command::query()->create([
        'onesi_box_id' => $this->box->id,
        'type' => CommandType::PlayStreamItem,
        'payload' => ['url' => 'https://stream.jw.org/x', 'ordinal' => 2],
        'priority' => 2,
        'status' => CommandStatus::Pending,
        'created_at' => now()->subHours(10),
    ]);

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->assertSet('url', '')
        ->assertSet('lastOrdinalSent', null);
});

it('restores reachedEnd true if latest error event has code E112', function () {
    $url = 'https://stream.jw.org/6311-4713-5379-2156';

    $command = Command::query()->create([
        'onesi_box_id' => $this->box->id,
        'type' => CommandType::PlayStreamItem,
        'payload' => ['url' => $url, 'ordinal' => 4],
        'priority' => 2,
        'status' => CommandStatus::Pending,
        'created_at' => now()->subHour(),
    ]);

    PlaybackEvent::query()->create([
        'onesi_box_id' => $this->box->id,
        'event' => PlaybackEventType::Error,
        'media_url' => $url,
        'media_type' => 'video',
        'error_code' => 'E112',
        'error_message' => 'Ordinal 5 exceeds playlist length 4',
        'created_at' => $command->created_at->addMinute(),
    ]);

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->assertSet('reachedEnd', true)
        ->assertSet('errorCode', 'E112');
});

it('restores errorCode from latest error event (E110/E111/E113) without setting reachedEnd', function () {
    $url = 'https://stream.jw.org/6311-4713-5379-2156';

    $command = Command::query()->create([
        'onesi_box_id' => $this->box->id,
        'type' => CommandType::PlayStreamItem,
        'payload' => ['url' => $url, 'ordinal' => 1],
        'priority' => 2,
        'status' => CommandStatus::Pending,
        'created_at' => now()->subHour(),
    ]);

    PlaybackEvent::query()->create([
        'onesi_box_id' => $this->box->id,
        'event' => PlaybackEventType::Error,
        'media_url' => $url,
        'media_type' => 'video',
        'error_code' => 'E111',
        'error_message' => 'No tiles found',
        'created_at' => $command->created_at->addMinute(),
    ]);

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->assertSet('errorCode', 'E111')
        ->assertSet('reachedEnd', false);
});
```

- [ ] **Step 2: Eseguire per confermare il fallimento**

Run: `vendor/bin/pest tests/Feature/Livewire/StreamPlayerTest.php`
Expected: FAIL — `Class App\Livewire\Dashboard\Controls\StreamPlayer not found`.

- [ ] **Step 3: Creare `app/Livewire/Dashboard/Controls/StreamPlayer.php` (skeleton + mount)**

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Concerns\HandlesOnesiBoxErrors;
use App\Enums\CommandType;
use App\Enums\PlaybackEventType;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Models\PlaybackEvent;
use App\Rules\JwStreamUrl;
use App\Services\OnesiBoxCommandServiceInterface;
use Illuminate\Contracts\View\View;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Livewire component per l'invio di comandi play_stream_item a un OnesiBox.
 *
 * Modello "self-limiting": la UI non conosce il numero di item della playlist —
 * reagisce all'evento error code E112 (ORDINAL_OUT_OF_RANGE) per disabilitare "Successivo".
 * Stato ricostruito al mount dalla tabella commands (ultimi 6 ore).
 */
class StreamPlayer extends Component
{
    use AuthorizesRequests;
    use HandlesOnesiBoxErrors;

    public OnesiBox $onesiBox;

    public string $url = '';

    public ?int $lastOrdinalSent = null;

    public ?string $errorCode = null;

    public bool $reachedEnd = false;

    public function mount(OnesiBox $onesiBox): void
    {
        $this->onesiBox = $onesiBox;

        $lastCommand = Command::query()
            ->where('onesi_box_id', $onesiBox->id)
            ->where('type', CommandType::PlayStreamItem)
            ->where('created_at', '>=', now()->subHours(6))
            ->latest()
            ->first();

        if ($lastCommand === null) {
            return;
        }

        $this->url = $lastCommand->payload['url'] ?? '';
        $this->lastOrdinalSent = $lastCommand->payload['ordinal'] ?? null;

        $lastEvent = PlaybackEvent::query()
            ->where('onesi_box_id', $onesiBox->id)
            ->where('media_url', $this->url)
            ->where('created_at', '>=', $lastCommand->created_at)
            ->orderByDesc('created_at')
            ->first();

        if ($lastEvent !== null && $lastEvent->event === PlaybackEventType::Error) {
            $this->errorCode = $lastEvent->error_code;
            if ($this->errorCode === 'E112') {
                $this->reachedEnd = true;
            }
        }
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.stream-player');
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    protected function rules(): array
    {
        return [
            'url' => ['required', new JwStreamUrl()],
        ];
    }
}
```

- [ ] **Step 4: Creare view Blade minima**

Creare `resources/views/livewire/dashboard/controls/stream-player.blade.php` con contenuto minimo (sarà arricchito nei task successivi):

```blade
<div>
    <div class="text-sm text-gray-500">Stream Playlist (in sviluppo)</div>
</div>
```

- [ ] **Step 5: Eseguire i test**

Run: `vendor/bin/pest tests/Feature/Livewire/StreamPlayerTest.php`
Expected: PASS 5/5 (tutti i test di mount/state restoration).

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Dashboard/Controls/StreamPlayer.php resources/views/livewire/dashboard/controls/stream-player.blade.php tests/Feature/Livewire/StreamPlayerTest.php
git commit -m "feat(livewire): StreamPlayer skeleton with state restoration from commands"
```

---

## Task 8: `StreamPlayer` — metodi `playFromStart`, `next`, `previous`, `stop`

**Files:**
- Modify: `app/Livewire/Dashboard/Controls/StreamPlayer.php`
- Test: `tests/Feature/Livewire/StreamPlayerTest.php`

- [ ] **Step 1: Aggiungere i test per le 4 azioni**

Aggiungere in coda a `tests/Feature/Livewire/StreamPlayerTest.php` (dopo gli `it()` esistenti):

```php
it('playFromStart validates empty url and shows error', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', '')
        ->call('playFromStart')
        ->assertHasErrors(['url' => 'required']);
});

it('playFromStart rejects non-stream.jw.org URL', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://www.jw.org/mediaitems/x')
        ->call('playFromStart')
        ->assertHasErrors(['url']);
});

it('playFromStart calls sendStreamItemCommand with ordinal 1', function () {
    $this->box->update(['last_heartbeat_at' => now()]);

    $service = $this->mock(OnesiBoxCommandServiceInterface::class);
    $service->shouldReceive('sendStreamItemCommand')
        ->once()
        ->with(
            \Mockery::on(fn ($box) => $box->id === $this->box->id),
            'https://stream.jw.org/6311-4713-5379-2156',
            1
        );

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/6311-4713-5379-2156')
        ->call('playFromStart')
        ->assertSet('lastOrdinalSent', 1)
        ->assertSet('reachedEnd', false)
        ->assertSet('errorCode', null);
});

it('next increments ordinal and calls service', function () {
    $this->box->update(['last_heartbeat_at' => now()]);

    $service = $this->mock(OnesiBoxCommandServiceInterface::class);
    $service->shouldReceive('sendStreamItemCommand')
        ->once()
        ->with(\Mockery::any(), 'https://stream.jw.org/x', 3);

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/x')
        ->set('lastOrdinalSent', 2)
        ->set('errorCode', 'E113')  // pre-esistente
        ->call('next')
        ->assertSet('lastOrdinalSent', 3)
        ->assertSet('errorCode', null);
});

it('next does nothing when reachedEnd is true', function () {
    $service = $this->mock(OnesiBoxCommandServiceInterface::class);
    $service->shouldNotReceive('sendStreamItemCommand');

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/x')
        ->set('lastOrdinalSent', 4)
        ->set('reachedEnd', true)
        ->call('next')
        ->assertSet('lastOrdinalSent', 4);  // invariato
});

it('previous decrements ordinal and resets reachedEnd', function () {
    $this->box->update(['last_heartbeat_at' => now()]);

    $service = $this->mock(OnesiBoxCommandServiceInterface::class);
    $service->shouldReceive('sendStreamItemCommand')
        ->once()
        ->with(\Mockery::any(), 'https://stream.jw.org/x', 2);

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/x')
        ->set('lastOrdinalSent', 3)
        ->set('reachedEnd', true)
        ->call('previous')
        ->assertSet('lastOrdinalSent', 2)
        ->assertSet('reachedEnd', false);
});

it('previous does nothing when lastOrdinalSent is 1', function () {
    $service = $this->mock(OnesiBoxCommandServiceInterface::class);
    $service->shouldNotReceive('sendStreamItemCommand');

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/x')
        ->set('lastOrdinalSent', 1)
        ->call('previous')
        ->assertSet('lastOrdinalSent', 1);
});

it('stop calls sendStopCommand', function () {
    $this->box->update(['last_heartbeat_at' => now()]);

    $service = $this->mock(OnesiBoxCommandServiceInterface::class);
    $service->shouldReceive('sendStopCommand')
        ->once()
        ->with(\Mockery::on(fn ($box) => $box->id === $this->box->id));

    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->call('stop');
});
```

- [ ] **Step 2: Eseguire per confermare il fallimento**

Run: `vendor/bin/pest tests/Feature/Livewire/StreamPlayerTest.php --filter="playFromStart|next|previous|stop"`
Expected: FAIL — metodi non ancora definiti.

- [ ] **Step 3: Implementare i 4 metodi in `StreamPlayer.php`**

Aggiungere dopo `mount()` e prima di `render()`:

```php
    public function playFromStart(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->validate();

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendStreamItemCommand(
                $this->onesiBox,
                $this->url,
                1
            ),
            successMessage: 'Playlist avviata'
        );

        $this->lastOrdinalSent = 1;
        $this->reachedEnd = false;
        $this->errorCode = null;
    }

    public function next(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        if ($this->reachedEnd || $this->lastOrdinalSent === null) {
            return;
        }

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendStreamItemCommand(
                $this->onesiBox,
                $this->url,
                $this->lastOrdinalSent + 1
            ),
            successMessage: 'Prossimo video inviato'
        );

        $this->lastOrdinalSent++;
        $this->errorCode = null;
    }

    public function previous(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        if ($this->lastOrdinalSent === null || $this->lastOrdinalSent <= 1) {
            return;
        }

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendStreamItemCommand(
                $this->onesiBox,
                $this->url,
                $this->lastOrdinalSent - 1
            ),
            successMessage: 'Video precedente inviato'
        );

        $this->lastOrdinalSent--;
        $this->reachedEnd = false;
        $this->errorCode = null;
    }

    public function stop(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendStopCommand($this->onesiBox),
            successMessage: 'Riproduzione interrotta'
        );
    }
```

- [ ] **Step 4: Eseguire i test**

Run: `vendor/bin/pest tests/Feature/Livewire/StreamPlayerTest.php`
Expected: tutti i test passano (i 5 di mount + gli 8 nuovi).

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Dashboard/Controls/StreamPlayer.php tests/Feature/Livewire/StreamPlayerTest.php
git commit -m "feat(livewire): add playFromStart/next/previous/stop methods to StreamPlayer"
```

---

## Task 9: `StreamPlayer` — Echo listener per eventi WebSocket

**Files:**
- Modify: `app/Livewire/Dashboard/Controls/StreamPlayer.php`
- Test: `tests/Feature/Livewire/StreamPlayerTest.php`

- [ ] **Step 1: Aggiungere i test per `handlePlaybackEvent`**

Aggiungere in coda a `tests/Feature/Livewire/StreamPlayerTest.php`:

```php
it('handlePlaybackEvent sets reachedEnd true when error code is E112', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/x')
        ->call('handlePlaybackEvent', [
            'event' => 'error',
            'media_url' => 'https://stream.jw.org/x',
            'media_type' => 'video',
            'error_code' => 'E112',
            'error_message' => 'Ordinal 5 exceeds playlist length 4',
        ])
        ->assertSet('errorCode', 'E112')
        ->assertSet('reachedEnd', true);
});

it('handlePlaybackEvent sets errorCode for E110/E111/E113 but not reachedEnd', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/x')
        ->call('handlePlaybackEvent', [
            'event' => 'error',
            'media_url' => 'https://stream.jw.org/x',
            'media_type' => 'video',
            'error_code' => 'E110',
            'error_message' => 'DNS timeout',
        ])
        ->assertSet('errorCode', 'E110')
        ->assertSet('reachedEnd', false);
});

it('handlePlaybackEvent ignores events with different media_url', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/current')
        ->set('errorCode', null)
        ->call('handlePlaybackEvent', [
            'event' => 'error',
            'media_url' => 'https://stream.jw.org/OTHER',
            'media_type' => 'video',
            'error_code' => 'E112',
        ])
        ->assertSet('errorCode', null)
        ->assertSet('reachedEnd', false);
});

it('handlePlaybackEvent does nothing on non-error events', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('url', 'https://stream.jw.org/x')
        ->set('errorCode', 'E110')  // preesistente
        ->call('handlePlaybackEvent', [
            'event' => 'started',
            'media_url' => 'https://stream.jw.org/x',
            'media_type' => 'video',
        ])
        ->assertSet('errorCode', 'E110');  // invariato
});

it('dismissError clears errorCode', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('errorCode', 'E110')
        ->call('dismissError')
        ->assertSet('errorCode', null);
});
```

- [ ] **Step 2: Eseguire per confermare il fallimento**

Run: `vendor/bin/pest tests/Feature/Livewire/StreamPlayerTest.php --filter="handlePlaybackEvent|dismissError"`
Expected: FAIL — metodi non ancora definiti.

- [ ] **Step 3: Implementare i metodi**

Aggiungere in `StreamPlayer.php` dopo `stop(...)`:

```php
    /**
     * Echo listener per eventi di playback broadcast dal PlaybackController.
     *
     * @param  array{event: string, media_url: string, media_type: string, error_code?: string|null, error_message?: string|null, occurred_at?: string}  $payload
     */
    #[On('echo-private:appliance.{onesiBox.serial_number},.playback.event-received')]
    public function handlePlaybackEvent(array $payload): void
    {
        // Filtro: ignoriamo eventi di un URL diverso da quello corrente
        if (($payload['media_url'] ?? null) !== $this->url) {
            return;
        }

        // Scope B: reagiamo solo a eventi 'error', ignoriamo started/completed/stopped
        if (($payload['event'] ?? null) !== 'error') {
            return;
        }

        $code = $payload['error_code'] ?? null;
        $this->errorCode = $code;

        if ($code === 'E112') {
            $this->reachedEnd = true;
        }
    }

    public function dismissError(): void
    {
        $this->errorCode = null;
    }
```

**Nota sintassi `#[On(...)]`**: Livewire 3 supporta interpolazione di property nel canale tramite `{propertyName}`. Se nel tuo progetto l'attributo risulta non accettare `{onesiBox.serial_number}` (sintassi Livewire 3 recente), una alternativa testata è usare `getListeners()` method:

```php
/** @return array<string, string> */
protected function getListeners(): array
{
    return [
        "echo-private:appliance.{$this->onesiBox->serial_number},.playback.event-received" => 'handlePlaybackEvent',
    ];
}
```

Questa alternativa è da preferire se il repository usa già quel pattern (verifica: `grep -rn "getListeners" app/Livewire`). Se non c'è precedente, prova prima con `#[On]` e fallback su `getListeners()` se fallisce.

- [ ] **Step 4: Eseguire i test**

Run: `vendor/bin/pest tests/Feature/Livewire/StreamPlayerTest.php`
Expected: PASS tutti i test del file (13+ totali).

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Dashboard/Controls/StreamPlayer.php tests/Feature/Livewire/StreamPlayerTest.php
git commit -m "feat(livewire): add Echo listener and dismissError to StreamPlayer"
```

---

## Task 10: View Blade completa per `StreamPlayer`

**Files:**
- Modify: `resources/views/livewire/dashboard/controls/stream-player.blade.php`

- [ ] **Step 1: Sostituire completamente la view**

Sovrascrivi il file `resources/views/livewire/dashboard/controls/stream-player.blade.php` con:

```blade
<div class="space-y-4 rounded-lg border border-gray-200 p-4 dark:border-gray-700">
    <flux:heading size="sm">Stream Playlist (JW Stream)</flux:heading>

    {{-- Form URL --}}
    <div class="flex gap-2">
        <flux:input
            wire:model="url"
            placeholder="https://stream.jw.org/XXXX-XXXX-XXXX-XXXX"
            :invalid="$errors->has('url')"
            class="flex-1"
        />
        <flux:button wire:click="playFromStart" variant="primary" icon="play">
            Avvia playlist
        </flux:button>
    </div>
    @error('url')
        <flux:text class="text-danger-600">{{ $message }}</flux:text>
    @enderror

    {{-- Controlli --}}
    @if($lastOrdinalSent !== null)
        <div class="flex items-center gap-2">
            <flux:button
                wire:click="previous"
                :disabled="$lastOrdinalSent <= 1"
                icon="chevron-left"
                size="sm"
            >
                Precedente
            </flux:button>

            <flux:text class="font-medium">
                Video corrente: {{ $lastOrdinalSent }}
            </flux:text>

            <flux:button
                wire:click="next"
                :disabled="$reachedEnd"
                icon-trailing="chevron-right"
                size="sm"
            >
                Successivo
            </flux:button>

            <flux:button
                wire:click="stop"
                variant="danger"
                icon="stop"
                size="sm"
            >
                Stop
            </flux:button>
        </div>
    @endif

    {{-- Banner errore --}}
    @if($errorCode !== null)
        @php
            $bannerVariant = match($errorCode) {
                'E112' => 'success',
                'E113' => 'warning',
                default => 'danger',
            };
            $bannerMessage = match($errorCode) {
                'E110' => 'Impossibile raggiungere JW Stream. Verifica la connessione del dispositivo.',
                'E111' => 'Playlist non caricata. L\'URL potrebbe essere errato o scaduto.',
                'E112' => 'Ultimo video della playlist raggiunto.',
                'E113' => 'Impossibile avviare il video. Il sito potrebbe essere cambiato — riprova o contatta supporto.',
                default => 'Errore sul dispositivo (codice: ' . $errorCode . ').',
            };
        @endphp

        <flux:callout variant="{{ $bannerVariant }}" :heading="$bannerMessage" icon="exclamation-triangle">
            <x-slot name="actions">
                <flux:button size="xs" wire:click="dismissError" variant="ghost">Chiudi</flux:button>
            </x-slot>
        </flux:callout>
    @endif
</div>
```

**Nota componenti Flux**: il progetto usa `flux:*` components (vedi `Flux\Flux::toast(...)` in `HandlesOnesiBoxErrors`). Se qualche componente sopra non esiste nella versione di Flux installata (es. `flux:callout` potrebbe essere Flux Pro), verificare e sostituire con componenti esistenti nel progetto (grep in `resources/views/livewire/dashboard/` per esempi).

Se `flux:callout` non esiste, sostituisci la sezione "Banner errore" con HTML + Tailwind classico:

```blade
@if($errorCode !== null)
    @php
        $bannerClass = match($errorCode) {
            'E112' => 'bg-green-50 border-green-200 text-green-900',
            'E113' => 'bg-yellow-50 border-yellow-200 text-yellow-900',
            default => 'bg-red-50 border-red-200 text-red-900',
        };
        $bannerMessage = match($errorCode) {
            'E110' => 'Impossibile raggiungere JW Stream. Verifica la connessione del dispositivo.',
            'E111' => 'Playlist non caricata. L\'URL potrebbe essere errato o scaduto.',
            'E112' => 'Ultimo video della playlist raggiunto.',
            'E113' => 'Impossibile avviare il video. Il sito potrebbe essere cambiato — riprova o contatta supporto.',
            default => 'Errore sul dispositivo (codice: ' . $errorCode . ').',
        };
    @endphp

    <div class="flex items-center justify-between border rounded-md p-3 {{ $bannerClass }}">
        <span>{{ $bannerMessage }}</span>
        <button type="button" wire:click="dismissError" class="text-sm underline">Chiudi</button>
    </div>
@endif
```

- [ ] **Step 2: Verificare il rendering via Livewire test**

Aggiungere un semplice test in `tests/Feature/Livewire/StreamPlayerTest.php`:

```php
it('renders the form with stream.jw.org placeholder', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->assertSee('stream.jw.org')
        ->assertSee('Avvia playlist');
});

it('renders the Precedente/Successivo/Stop controls when lastOrdinalSent is set', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('lastOrdinalSent', 2)
        ->assertSee('Precedente')
        ->assertSee('Successivo')
        ->assertSee('Stop');
});

it('renders the error banner with E112 message when reachedEnd', function () {
    Livewire::test(StreamPlayer::class, ['onesiBox' => $this->box])
        ->set('errorCode', 'E112')
        ->set('reachedEnd', true)
        ->assertSee('Ultimo video della playlist raggiunto');
});
```

- [ ] **Step 3: Eseguire i test**

Run: `vendor/bin/pest tests/Feature/Livewire/StreamPlayerTest.php`
Expected: tutti i test passano (inclusi i 3 nuovi di rendering).

- [ ] **Step 4: Commit**

```bash
git add resources/views/livewire/dashboard/controls/stream-player.blade.php tests/Feature/Livewire/StreamPlayerTest.php
git commit -m "feat(livewire): add full Blade view for StreamPlayer component"
```

---

## Task 11: Integrare `StreamPlayer` nel dashboard

**Files:**
- Modify: `resources/views/livewire/dashboard/onesi-box-detail.blade.php`

- [ ] **Step 1: Localizzare il punto di montaggio**

Il file si trova a `resources/views/livewire/dashboard/onesi-box-detail.blade.php`. Alla riga 153 (approssimativa, verifica con grep) c'è:

```blade
<livewire:dashboard.controls.video-player :onesiBox="$onesiBox" wire:key="video-{{ $onesiBox->id }}" />
```

- [ ] **Step 2: Aggiungere il nuovo component immediatamente sotto**

Subito dopo la riga del `video-player`, aggiungere:

```blade
<livewire:dashboard.controls.stream-player :onesiBox="$onesiBox" wire:key="stream-{{ $onesiBox->id }}" />
```

- [ ] **Step 3: Verificare visualmente avviando il server dev**

Se l'ambiente è configurato (`.env` backend, Herd/serve, ecc.):

Run: `php artisan serve` (in un terminale dedicato) o usa Herd esistente
Naviga a: dashboard admin, sezione OnesiBox detail
Expected: il nuovo blocco "Stream Playlist (JW Stream)" è visibile sotto al video player esistente.

Se non hai l'ambiente locale pronto, skippa questo step e segnala nel commit.

- [ ] **Step 4: Lint + full test suite**

Run: `vendor/bin/pint`
Run: `vendor/bin/pest`
Expected: pulito / tutti passano.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/dashboard/onesi-box-detail.blade.php
git commit -m "feat(dashboard): mount StreamPlayer Livewire component in OnesiBox detail"
```

---

## Task 12: Documentazione smoke test E2E

**Files:**
- Create or Modify: `docs/features/stream-playlist.md` (nuovo) oppure sezione in `docs/ONESIBOX_USER_GUIDE.md` (se preferito)

- [ ] **Step 1: Decidere il file di destinazione**

Verificare se esiste già una convenzione:
- Se `docs/features/` contiene altre pagine feature-specifiche, crea `docs/features/stream-playlist.md`.
- Altrimenti, appendere una sezione a `docs/ONESIBOX_USER_GUIDE.md`.

Per default, creare il nuovo file dedicato `docs/features/stream-playlist.md`.

- [ ] **Step 2: Scrivere la documentazione**

Contenuto di `docs/features/stream-playlist.md`:

```markdown
# Stream Playlist (JW Stream)

Emissione del comando `play_stream_item` verso un OnesiBox per riprodurre il video N-esimo di una playlist su `https://stream.jw.org/`.

## Prerequisiti

- OnesiBox client aggiornato alla versione che include il comando `play_stream_item` (client repo `onesi-box`, branch `feature/play-stream-item` o successive).
- Operatore autorizzato con permesso `control` sul dispositivo.
- OnesiBox online (vedi indicatore online nel dashboard).

## Flusso operativo (UI)

1. Dashboard admin → seleziona l'OnesiBox target (pagina detail del dispositivo).
2. Individua il pannello "Stream Playlist (JW Stream)".
3. Inserisci l'URL di share della playlist (es. `https://stream.jw.org/6311-4713-5379-2156`).
4. Clicca **Avvia playlist** → parte il primo video, il pannello mostra "Video corrente: 1".
5. Per passare al video successivo, clicca **Successivo** → il client OnesiBox chiude il video corrente, ri-naviga, avvia il video N+1.
6. Per tornare al precedente, clicca **Precedente**.
7. Quando raggiungi l'ultimo video della playlist, premendo ancora **Successivo** apparirà il banner verde "Ultimo video della playlist raggiunto" e il bottone verrà disabilitato. Per tornare indietro, premi **Precedente**.
8. Per fermare la riproduzione e tornare allo standby, clicca **Stop**.

## Comportamento dopo refresh della pagina

Il pannello ricostruisce lo stato (URL + ordinale + eventuale fine playlist) dai comandi inviati nelle ultime 6 ore. Se l'ultimo comando supera le 6 ore, il pannello si presenta vuoto.

## Errori possibili

| Banner | Codice | Cosa fare |
|---|---|---|
| Rosso: "Impossibile raggiungere JW Stream" | E110 | Verifica connessione internet del dispositivo |
| Rosso: "Playlist non caricata" | E111 | L'URL potrebbe essere scaduto/errato — chiedere nuovo share link |
| Verde: "Ultimo video della playlist raggiunto" | E112 | Informativo, non è un errore |
| Giallo: "Impossibile avviare il video" | E113 | Il sito JW Stream potrebbe essere cambiato — riprovare o segnalare supporto |
| Rosso: "OnesiBox non raggiungibile" | offline | Verifica il dispositivo è acceso e online |

## Limitazioni note

- **Solo share link pubblici**: URL che richiedono login personale JW non sono supportati.
- **Gap di 5-10 secondi tra un video e l'altro**: l'OnesiBox deve ri-navigare alla SPA JW Stream e cliccare il nuovo tile. Inevitabile con questo approccio DOM-driven.
- **Massimo 50 video per playlist** (limite di validazione).
- **Se JW Stream cambia struttura DOM**: il comando fallisce pulito con errore E111. Richiede aggiornamento firmware OnesiBox.

## Smoke test post-deploy

1. Accedi alla dashboard admin con utente autorizzato.
2. Seleziona un OnesiBox di test online (dev o staging).
3. Copia un share link valido (es. un'assemblea recente dal pannello JW Stream).
4. Inserisci l'URL nel pannello Stream Playlist → **Avvia playlist**.
5. Verifica sul dispositivo: navigazione automatica → primo video in playback fullscreen.
6. Clicca **Successivo** → secondo video dopo ~5-10s.
7. Clicca **Precedente** → primo video.
8. Clicca **Successivo** più volte oltre l'ultimo: banner verde appare, bottone disabilitato.
9. Clicca **Stop**: dispositivo torna in standby.
10. Refresh browser: stato conservato (URL + ordinale corretto).
```

- [ ] **Step 3: Commit**

```bash
git add docs/features/stream-playlist.md
git commit -m "docs: add Stream Playlist feature documentation"
```

---

## Self-review notes (completata prima del commit del plan)

**Spec coverage:** ogni requisito dello spec ha almeno un task:
- Nuovo enum case → Task 4
- Nuovo service method → Task 6 (+ interface in stessa task)
- JwStreamUrl rule → Task 5
- StreamPlayer Livewire + view → Task 7/8/9/10
- Shared plumbing (error_code, broadcast) → Task 1/2/3
- Integrazione dashboard → Task 11
- Smoke test docs → Task 12
- Testing: unit Rules (Task 5), Unit Actions (Task 2), Feature API (Task 3), Feature Service (Task 6), Feature Livewire (Task 7/8/9/10)

**Placeholder scan:** ogni step ha codice completo o comandi espliciti. Due note di ambiente:
- Task 6: `isOnline()` varia per repo — indicato di verificare il metodo e adattare factory state. Non è placeholder ma cautela contestuale.
- Task 9: sintassi `#[On(echo-private:...)]` ha alternativa documentata `getListeners()` in caso Livewire 3 non supporti l'interpolazione property. Cautela esplicita con soluzione fallback.
- Task 10: alternativa HTML+Tailwind documentata se `flux:callout` non è nella versione Flux installata.

**Type consistency:** verificato:
- Metodo service `sendStreamItemCommand(OnesiBox, string, int): void` coerente tra interface, implementazione, chiamate in Livewire, test.
- Property `StreamPlayer::$url/$lastOrdinalSent/$errorCode/$reachedEnd` tipi coerenti tra dichiarazione, mount, actions, listener, view Blade.
- Evento broadcast `broadcastAs() = 'playback.event-received'` coerente con listener `#[On('echo-private:...,.playback.event-received')]`.
