# Dashboard Detail Mobile Redesign — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Ristrutturare mobile-first la pagina `GET /dashboard/{onesiBox}` con header sticky, hero card dinamica (idle/media/call/offline), corpo ad accordion, e bottom bar sticky per azioni rapide (Stop / Volume / Nuovo / Chiama).

**Architecture:** 3 nuovi componenti Livewire isolati (`HeroCard`, `BottomBar`, `QuickPlaySheet`) che leggono lo stato dal parent `OnesiBoxDetail` tramite computed properties e nuove `sendPauseCommand`/`sendResumeCommand` sul `OnesiBoxCommandServiceInterface`. Niente modifiche allo schema DB, ai permessi o agli altri componenti Livewire esistenti, che vengono riusati tali e quali dentro `flux:accordion`.

**Tech Stack:** Laravel 12, Livewire 4, Flux UI 2, Tailwind CSS 4, Pest 4, PHPStan/Larastan 3, Pint.

**Spec di riferimento:** `docs/superpowers/specs/2026-04-22-dashboard-detail-mobile-redesign-design.md`

**Branch:** `feat/dashboard-detail-mobile-redesign` (già creato sul commit `1dbf98e`).

---

## File Structure

### Da creare

| Path | Responsabilità |
|------|----------------|
| `app/Livewire/Dashboard/Controls/HeroCard.php` | Componente Livewire per il box di stato (4 varianti idle/media/call/offline) e le sue azioni rapide (pause/resume/stop/leave-zoom). |
| `resources/views/livewire/dashboard/controls/hero-card.blade.php` | View delle 4 varianti hero. |
| `app/Livewire/Dashboard/Controls/BottomBar.php` | Componente Livewire per la bottom bar sticky (4 slot: Stop / Volume / Nuovo / Chiama). |
| `resources/views/livewire/dashboard/controls/bottom-bar.blade.php` | View bottom bar con `flux:popover` per volume e dispatch eventi. |
| `app/Livewire/Dashboard/Controls/QuickPlaySheet.php` | Componente Livewire per il bottom sheet "Riproduci…" con 5 tab (Audio URL / Video URL / Stream / Playlist salvate / Chiamata Zoom). |
| `resources/views/livewire/dashboard/controls/quick-play-sheet.blade.php` | View bottom sheet con `flux:modal` variant `flyout`. |
| `tests/Feature/Livewire/Dashboard/Controls/HeroCardTest.php` | Test Livewire (rendering 4 stati, azioni). |
| `tests/Feature/Livewire/Dashboard/Controls/BottomBarTest.php` | Test Livewire (visibilità, dispatch comandi). |
| `tests/Feature/Livewire/Dashboard/Controls/QuickPlaySheetTest.php` | Test Livewire (aperture tab, submit). |
| `tests/Browser/DashboardDetailMobileTest.php` | Pest 4 browser smoke test viewport 390×844. |

### Da modificare

| Path | Modifica |
|------|---------|
| `app/Services/OnesiBoxCommandServiceInterface.php` | Aggiungere `sendPauseCommand` e `sendResumeCommand`. |
| `app/Services/OnesiBoxCommandService.php` | Implementare i due nuovi metodi mappati su `CommandType::PauseMedia` / `ResumeMedia`. |
| `app/Livewire/Dashboard/OnesiBoxDetail.php` | Aggiungere 4 computed: `heroState`, `isInCall`, `isMediaPaused`, `accordionDefaults`. |
| `resources/views/livewire/dashboard/onesi-box-detail.blade.php` | Rewrite completo della struttura: sticky header minimale → `HeroCard` → `flux:accordion` per tutti i sottocomponenti → `BottomBar` + `QuickPlaySheet` mount. |
| `tests/Feature/Livewire/Dashboard/OnesiBoxDetailTest.php` (se esiste — se no, crearlo contestualmente) | Adeguare assertion DOM alla nuova struttura (hero / accordion / bottom bar). |
| `tests/Feature/Services/OnesiBoxCommandServiceTest.php` (se esiste — se no, estendere quello esistente) | Aggiungere test per i nuovi metodi pause/resume. |

---

## Task di lavoro

### Task 1: Verifica baseline

**Files:** nessuno modificato.

- [ ] **Step 1: Verifica di essere sul branch giusto**

Run:
```bash
git branch --show-current
```
Expected output: `feat/dashboard-detail-mobile-redesign`

- [ ] **Step 2: Esegui l'intera test suite per confermare lo stato verde di partenza**

Run:
```bash
php artisan test --compact
```
Expected: tutti i test passano (566+ test verdi). Se qualche test è rosso prima di iniziare, fermati e segnala.

- [ ] **Step 3: Verifica stile e analisi statica pulite**

Run:
```bash
vendor/bin/pint --test --format agent && vendor/bin/phpstan analyse --memory-limit=2G
```
Expected: zero differenze Pint, zero errori PHPStan. Se PHPStan mostra errori già esistenti in `main`, annotarli come pre-esistenti e non introdurne di nuovi.

---

### Task 2: Estendere `OnesiBoxCommandServiceInterface` con `sendPauseCommand`

**Files:**
- Modify: `app/Services/OnesiBoxCommandServiceInterface.php`
- Modify: `app/Services/OnesiBoxCommandService.php`
- Test: `tests/Feature/Services/OnesiBoxCommandServiceTest.php` (verificare esistenza prima; se non esiste crearla)

- [ ] **Step 1: Se il test file non esiste, crearlo**

Run:
```bash
test -f tests/Feature/Services/OnesiBoxCommandServiceTest.php || php artisan make:test --pest Services/OnesiBoxCommandServiceTest --no-interaction
```

- [ ] **Step 2: Scrivi il test che fallisce**

Apri `tests/Feature/Services/OnesiBoxCommandServiceTest.php` e aggiungi (in coda al file):

```php
use App\Enums\CommandType;
use App\Models\Command;
use App\Models\OnesiBox;
use App\Services\OnesiBoxCommandServiceInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('sendPauseCommand enqueues a PauseMedia command', function () {
    $onesiBox = OnesiBox::factory()->online()->create();

    /** @var OnesiBoxCommandServiceInterface $service */
    $service = app(OnesiBoxCommandServiceInterface::class);

    $service->sendPauseCommand($onesiBox);

    expect(Command::query()->where('onesi_box_id', $onesiBox->id)->count())->toBe(1);
    expect(Command::query()->latest('id')->first()->type)->toBe(CommandType::PauseMedia);
});
```

- [ ] **Step 3: Esegui il test per vederlo fallire**

Run:
```bash
php artisan test --compact --filter='sendPauseCommand enqueues'
```
Expected: FAIL con messaggio tipo *Method sendPauseCommand does not exist*.

- [ ] **Step 4: Aggiungi il metodo all'interfaccia**

In `app/Services/OnesiBoxCommandServiceInterface.php`, dopo `sendLeaveZoomCommand`:

```php
    /**
     * Invia comando di pausa sul media corrente.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendPauseCommand(OnesiBox $onesiBox): void;
```

- [ ] **Step 5: Implementa il metodo nel service**

In `app/Services/OnesiBoxCommandService.php`, accanto agli altri metodi `send*`:

```php
    public function sendPauseCommand(OnesiBox $onesiBox): void
    {
        $this->sendCommand($onesiBox, CommandType::PauseMedia);
    }
```

- [ ] **Step 6: Rilancia il test**

Run:
```bash
php artisan test --compact --filter='sendPauseCommand enqueues'
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Services/OnesiBoxCommandServiceInterface.php \
        app/Services/OnesiBoxCommandService.php \
        tests/Feature/Services/OnesiBoxCommandServiceTest.php
git commit -m "feat(commands): add sendPauseCommand to OnesiBoxCommandService"
```

---

### Task 3: Aggiungere `sendResumeCommand` al service

**Files:**
- Modify: `app/Services/OnesiBoxCommandServiceInterface.php`
- Modify: `app/Services/OnesiBoxCommandService.php`
- Test: `tests/Feature/Services/OnesiBoxCommandServiceTest.php`

- [ ] **Step 1: Scrivi il test che fallisce**

Aggiungi in coda a `tests/Feature/Services/OnesiBoxCommandServiceTest.php`:

```php
it('sendResumeCommand enqueues a ResumeMedia command', function () {
    $onesiBox = OnesiBox::factory()->online()->create();

    /** @var OnesiBoxCommandServiceInterface $service */
    $service = app(OnesiBoxCommandServiceInterface::class);

    $service->sendResumeCommand($onesiBox);

    expect(Command::query()->latest('id')->first()->type)->toBe(CommandType::ResumeMedia);
});
```

- [ ] **Step 2: Verifica che fallisca**

Run:
```bash
php artisan test --compact --filter='sendResumeCommand enqueues'
```
Expected: FAIL.

- [ ] **Step 3: Aggiungi il metodo all'interfaccia**

In `app/Services/OnesiBoxCommandServiceInterface.php`, subito dopo `sendPauseCommand`:

```php
    /**
     * Invia comando di ripresa sul media corrente precedentemente messo in pausa.
     *
     * @throws OnesiBoxOfflineException
     * @throws OnesiBoxCommandException
     */
    public function sendResumeCommand(OnesiBox $onesiBox): void;
```

- [ ] **Step 4: Implementa nel service**

In `app/Services/OnesiBoxCommandService.php`:

```php
    public function sendResumeCommand(OnesiBox $onesiBox): void
    {
        $this->sendCommand($onesiBox, CommandType::ResumeMedia);
    }
```

- [ ] **Step 5: Verifica passaggio**

Run:
```bash
php artisan test --compact --filter='sendResumeCommand enqueues'
```
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Services/OnesiBoxCommandServiceInterface.php \
        app/Services/OnesiBoxCommandService.php \
        tests/Feature/Services/OnesiBoxCommandServiceTest.php
git commit -m "feat(commands): add sendResumeCommand to OnesiBoxCommandService"
```

---

### Task 4: Computed `heroState` su `OnesiBoxDetail`

**Files:**
- Modify: `app/Livewire/Dashboard/OnesiBoxDetail.php`
- Test: `tests/Feature/Livewire/Dashboard/OnesiBoxDetailTest.php` (crearlo se non esiste)

Scopo: una sola property (`heroState`) che ritorna `'offline' | 'call' | 'media' | 'idle'` nell'ordine di precedenza (offline batte tutto, poi call, poi media, infine idle).

- [ ] **Step 1: Se il test file non esiste, crearlo**

Run:
```bash
test -f tests/Feature/Livewire/Dashboard/OnesiBoxDetailTest.php || php artisan make:test --pest Livewire/Dashboard/OnesiBoxDetailTest --no-interaction
```

- [ ] **Step 2: Scrivi i test che falliscono**

Aggiungi in `tests/Feature/Livewire/Dashboard/OnesiBoxDetailTest.php`:

```php
use App\Enums\OnesiBoxStatus;
use App\Livewire\Dashboard\OnesiBoxDetail;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('heroState returns offline when the box is offline', function () {
    $box = OnesiBox::factory()->offline()->create();
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('heroState', 'offline');
});

it('heroState returns call when the box is in a Zoom call', function () {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '1234567890',
    ]);
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('heroState', 'call');
});

it('heroState returns media when a media is playing and no call is active', function () {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/song.mp3',
        'current_media_type' => 'audio',
    ]);
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('heroState', 'media');
});

it('heroState returns idle when the box is online and nothing is playing', function () {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Idle,
    ]);
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('heroState', 'idle');
});
```

**Nota:** controlla le factory state disponibili in `database/factories/OnesiBoxFactory.php` prima di eseguire. Se `online()`/`offline()` non esistono, adatta scrivendo inline `['last_seen_at' => now()]` per online e `['last_seen_at' => now()->subHour()]` per offline, mantenendo la semantica. Se i valori di `status` sopra non corrispondono ai case dell'enum `OnesiBoxStatus`, ispeziona il file `app/Enums/OnesiBoxStatus.php` e sostituisci con i valori corretti.

- [ ] **Step 3: Verifica che falliscano**

Run:
```bash
php artisan test --compact --filter='heroState'
```
Expected: 4 test FAIL.

- [ ] **Step 4: Implementa la computed**

In `app/Livewire/Dashboard/OnesiBoxDetail.php`, aggiungi dopo `currentMeetingInfo()`:

```php
    /**
     * Get the current hero card variant.
     *
     * @return 'offline'|'call'|'media'|'idle'
     */
    #[Computed]
    public function heroState(): string
    {
        if (! $this->isOnline) {
            return 'offline';
        }

        if ($this->onesiBox->status === OnesiBoxStatus::Calling
            && $this->onesiBox->current_meeting_id !== null) {
            return 'call';
        }

        if ($this->onesiBox->current_media_url !== null) {
            return 'media';
        }

        return 'idle';
    }
```

- [ ] **Step 5: Verifica passaggio**

Run:
```bash
php artisan test --compact --filter='heroState'
```
Expected: 4 PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Dashboard/OnesiBoxDetail.php \
        tests/Feature/Livewire/Dashboard/OnesiBoxDetailTest.php
git commit -m "feat(dashboard): add heroState computed to OnesiBoxDetail"
```

---

### Task 5: Computed `isInCall`, `isMediaPaused`, `accordionDefaults`

**Files:**
- Modify: `app/Livewire/Dashboard/OnesiBoxDetail.php`
- Test: `tests/Feature/Livewire/Dashboard/OnesiBoxDetailTest.php`

- [ ] **Step 1: Scrivi i test che falliscono**

Aggiungi in `OnesiBoxDetailTest.php`:

```php
use App\Enums\CommandStatus;
use App\Enums\PlaybackEventType;
use App\Models\Command;
use App\Models\PlaybackEvent;
use App\Models\PlaybackSession;

it('isInCall reflects the Calling status', function () {
    $boxInCall = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '1234567890',
    ]);
    $boxInCall->caregivers()->attach($this->user, ['permission' => 'full']);
    livewire(OnesiBoxDetail::class, ['onesiBox' => $boxInCall])->assertSet('isInCall', true);

    $boxIdle = OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Idle]);
    $boxIdle->caregivers()->attach($this->user, ['permission' => 'full']);
    livewire(OnesiBoxDetail::class, ['onesiBox' => $boxIdle])->assertSet('isInCall', false);
});

it('isMediaPaused is true only when the last PlaybackEvent is Paused', function () {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/song.mp3',
        'current_media_type' => 'audio',
    ]);
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    PlaybackEvent::factory()->for($box, 'onesiBox')->create([
        'event_type' => PlaybackEventType::Started,
    ]);
    PlaybackEvent::factory()->for($box, 'onesiBox')->create([
        'event_type' => PlaybackEventType::Paused,
    ]);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSet('isMediaPaused', true);
});

it('accordionDefaults opens session-in-progress when an active session exists', function () {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    PlaybackSession::factory()->for($box, 'onesiBox')->create([
        'status' => \App\Enums\PlaybackSessionStatus::Active,
    ]);

    $defaults = livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->get('accordionDefaults');

    expect($defaults)->toHaveKey('session', true);
});

it('accordionDefaults opens command-queue when pending commands exist', function () {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    Command::factory()->for($box, 'onesiBox')->create([
        'status' => CommandStatus::Pending,
    ]);

    $defaults = livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->get('accordionDefaults');

    expect($defaults)->toHaveKey('commands', true);
});
```

**Nota:** verifica i nomi esatti delle factory `PlaybackEvent::factory()`, `PlaybackSession::factory()` e `Command::factory()` e dei relativi `state()` eventualmente disponibili. Se l'enum `PlaybackEventType` ha case diversi (es. `Paused`), adeguare. Se non esiste una factory per `PlaybackEvent`, crearla in `database/factories/PlaybackEventFactory.php` con lo strict minimum di campi, poi proseguire.

- [ ] **Step 2: Verifica che falliscano**

Run:
```bash
php artisan test --compact --filter='isInCall|isMediaPaused|accordionDefaults'
```
Expected: FAIL su tutti e 4.

- [ ] **Step 3: Implementa le computed**

In `app/Livewire/Dashboard/OnesiBoxDetail.php`, accanto alla `heroState`:

```php
    /**
     * Check if the OnesiBox is currently in a Zoom call.
     */
    #[Computed]
    public function isInCall(): bool
    {
        return $this->onesiBox->status === OnesiBoxStatus::Calling;
    }

    /**
     * Determine whether the current media is paused
     * by inspecting the latest PlaybackEvent for this OnesiBox.
     */
    #[Computed]
    public function isMediaPaused(): bool
    {
        $latest = $this->onesiBox
            ->playbackEvents()
            ->latest('id')
            ->first();

        return $latest?->event_type === \App\Enums\PlaybackEventType::Paused;
    }

    /**
     * Default open/closed state for each accordion in the body.
     *
     * @return array<string,bool>
     */
    #[Computed]
    public function accordionDefaults(): array
    {
        return [
            'session' => $this->onesiBox->playbackSessions()
                ->where('status', \App\Enums\PlaybackSessionStatus::Active)
                ->exists(),
            'commands' => $this->onesiBox->commands()
                ->whereIn('status', [
                    \App\Enums\CommandStatus::Pending,
                    \App\Enums\CommandStatus::Sent,
                ])
                ->exists(),
            'playlists' => false,
            'contacts' => false,
            'meetings' => false,
        ];
    }
```

**Nota:** verifica che `playbackEvents()` e `playbackSessions()` siano relazioni definite sul model `OnesiBox`. Se il nome è diverso (es. `playback_events`), adatta. Lo stesso per il case `Sent` di `CommandStatus` — ispeziona `app/Enums/CommandStatus.php` e usa il valore reale (potrebbe essere `Dispatched`/`InFlight`).

- [ ] **Step 4: Verifica passaggio**

Run:
```bash
php artisan test --compact --filter='isInCall|isMediaPaused|accordionDefaults'
```
Expected: 4 PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Livewire/Dashboard/OnesiBoxDetail.php tests/Feature/Livewire/Dashboard/OnesiBoxDetailTest.php
git commit -m "feat(dashboard): add isInCall, isMediaPaused, accordionDefaults computeds"
```

---

### Task 6: `HeroCard` — scheletro + variante `idle`

**Files:**
- Create: `app/Livewire/Dashboard/Controls/HeroCard.php`
- Create: `resources/views/livewire/dashboard/controls/hero-card.blade.php`
- Create: `tests/Feature/Livewire/Dashboard/Controls/HeroCardTest.php`

- [ ] **Step 1: Genera classe e test**

Run:
```bash
php artisan make:livewire Dashboard/Controls/HeroCard --no-interaction && \
php artisan make:test --pest Livewire/Dashboard/Controls/HeroCardTest --no-interaction
```

- [ ] **Step 2: Scrivi il test che fallisce**

In `tests/Feature/Livewire/Dashboard/Controls/HeroCardTest.php`:

```php
<?php

declare(strict_types=1);

use App\Enums\OnesiBoxStatus;
use App\Livewire\Dashboard\Controls\HeroCard;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('renders the idle variant with "in attesa" copy when state is idle', function () {
    $box = OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Idle]);

    livewire(HeroCard::class, ['onesiBox' => $box, 'state' => 'idle'])
        ->assertSee('In attesa')
        ->assertSeeHtml('data-hero-state="idle"');
});
```

- [ ] **Step 3: Verifica che fallisca**

Run:
```bash
php artisan test --compact --filter='HeroCardTest'
```
Expected: FAIL (il template è ancora lo scheletro generato).

- [ ] **Step 4: Implementa la classe**

Sovrascrivi `app/Livewire/Dashboard/Controls/HeroCard.php`:

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Models\OnesiBox;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class HeroCard extends Component
{
    #[Locked]
    public OnesiBox $onesiBox;

    /** @var 'offline'|'call'|'media'|'idle' */
    #[Locked]
    public string $state = 'idle';

    public bool $isPaused = false;

    public function render(): View
    {
        return view('livewire.dashboard.controls.hero-card');
    }
}
```

- [ ] **Step 5: Implementa la view (solo idle per ora; gli altri stati vengono nei task successivi)**

Sovrascrivi `resources/views/livewire/dashboard/controls/hero-card.blade.php`:

```blade
<div data-hero-state="{{ $state }}" class="rounded-lg border border-zinc-200 dark:border-zinc-700 bg-white dark:bg-zinc-800 p-4" aria-live="polite">
    @if($state === 'idle')
        <div class="flex items-center gap-2">
            <span class="h-2.5 w-2.5 rounded-full bg-green-500"></span>
            <flux:text class="text-sm text-zinc-500 dark:text-zinc-400">Online · In attesa</flux:text>
        </div>
        <flux:text class="mt-2 text-xs text-zinc-400 dark:text-zinc-500">
            Ultimo contatto: {{ $onesiBox->last_seen_at?->diffForHumans() ?? '—' }}
        </flux:text>
    @else
        {{-- altre varianti implementate nei task successivi --}}
    @endif
</div>
```

- [ ] **Step 6: Verifica passaggio**

Run:
```bash
php artisan test --compact --filter='HeroCardTest'
```
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Dashboard/Controls/HeroCard.php \
        resources/views/livewire/dashboard/controls/hero-card.blade.php \
        tests/Feature/Livewire/Dashboard/Controls/HeroCardTest.php
git commit -m "feat(dashboard): add HeroCard skeleton with idle variant"
```

---

### Task 7: `HeroCard` — variante `media` con progress bar

**Files:**
- Modify: `resources/views/livewire/dashboard/controls/hero-card.blade.php`
- Modify: `tests/Feature/Livewire/Dashboard/Controls/HeroCardTest.php`

- [ ] **Step 1: Scrivi il test che fallisce**

Aggiungi a `HeroCardTest.php`:

```php
it('renders the media variant with title, type label and progress bar', function () {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://www.youtube.com/watch?v=abc',
        'current_media_type' => 'audio',
        'current_media_title' => 'Ave Maria',
        'current_media_position' => 60,
        'current_media_duration' => 180,
    ]);

    livewire(HeroCard::class, ['onesiBox' => $box, 'state' => 'media'])
        ->assertSee('AUDIO')
        ->assertSee('Ave Maria')
        ->assertSeeHtml('data-hero-state="media"')
        ->assertSeeHtml('role="progressbar"');
});

it('does not render progress bar when position/duration are null', function () {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/x.mp3',
        'current_media_type' => 'audio',
    ]);

    livewire(HeroCard::class, ['onesiBox' => $box, 'state' => 'media'])
        ->assertDontSeeHtml('role="progressbar"');
});
```

- [ ] **Step 2: Verifica che falliscano**

Run:
```bash
php artisan test --compact --filter='HeroCardTest'
```
Expected: i nuovi 2 test FAIL.

- [ ] **Step 3: Estendi la view con il ramo `media`**

In `resources/views/livewire/dashboard/controls/hero-card.blade.php`, sostituisci il placeholder `{{-- altre varianti… --}}` con:

```blade
    @elseif($state === 'media')
        @php
            $type = strtoupper((string) $onesiBox->current_media_type);
            $host = $onesiBox->current_media_url
                ? (parse_url($onesiBox->current_media_url, PHP_URL_HOST) ?? $onesiBox->current_media_url)
                : null;
            $title = $onesiBox->current_media_title ?: $host;
            $pos = $onesiBox->current_media_position;
            $dur = $onesiBox->current_media_duration;
            $pct = ($pos !== null && $dur !== null && $dur > 0) ? min(100, (int) round($pos / $dur * 100)) : null;
        @endphp

        <div class="flex items-center gap-2">
            <span class="inline-flex items-center rounded px-2 py-0.5 text-[10px] font-semibold tracking-wide bg-green-100 text-green-800 dark:bg-green-900/40 dark:text-green-300">
                ▶ {{ $type }}
            </span>
        </div>
        <flux:heading size="lg" class="mt-2 line-clamp-2 break-words">{{ $title }}</flux:heading>
        @if($host)
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">Fonte: {{ $host }}</flux:text>
        @endif

        @if($pct !== null)
            <div class="mt-3 flex items-center gap-2">
                <div class="h-2 flex-1 overflow-hidden rounded-full bg-zinc-200 dark:bg-zinc-700" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $pct }}">
                    <div class="h-full bg-green-500" style="width: {{ $pct }}%"></div>
                </div>
                <flux:text class="text-xs tabular-nums text-zinc-500 dark:text-zinc-400">
                    {{ gmdate($dur >= 3600 ? 'H:i:s' : 'i:s', (int) $pos) }} / {{ gmdate($dur >= 3600 ? 'H:i:s' : 'i:s', (int) $dur) }}
                </flux:text>
            </div>
        @endif
```

- [ ] **Step 4: Verifica passaggio**

Run:
```bash
php artisan test --compact --filter='HeroCardTest'
```
Expected: tutti PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/dashboard/controls/hero-card.blade.php \
        tests/Feature/Livewire/Dashboard/Controls/HeroCardTest.php
git commit -m "feat(dashboard): render media variant with progress bar in HeroCard"
```

---

### Task 8: `HeroCard` — variante `call`

**Files:**
- Modify: `resources/views/livewire/dashboard/controls/hero-card.blade.php`
- Modify: `tests/Feature/Livewire/Dashboard/Controls/HeroCardTest.php`

- [ ] **Step 1: Scrivi il test che fallisce**

Aggiungi a `HeroCardTest.php`:

```php
it('renders the call variant with meeting id', function () {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '123456789',
        'current_meeting_joined_at' => now()->subMinutes(12),
    ]);

    livewire(HeroCard::class, ['onesiBox' => $box, 'state' => 'call'])
        ->assertSee('Chiamata in corso')
        ->assertSee('123456789')
        ->assertSeeHtml('data-hero-state="call"');
});
```

- [ ] **Step 2: Verifica che fallisca**

Run:
```bash
php artisan test --compact --filter='renders the call variant'
```
Expected: FAIL.

- [ ] **Step 3: Estendi la view**

Dopo il ramo `media` (e prima di `else`/`endif`):

```blade
    @elseif($state === 'call')
        <div class="flex items-center gap-2 text-blue-700 dark:text-blue-300">
            <flux:icon name="phone" class="h-5 w-5" />
            <flux:text class="font-medium">Chiamata in corso</flux:text>
        </div>
        <flux:heading size="lg" class="mt-2">Meeting {{ $onesiBox->current_meeting_id }}</flux:heading>
        @if($onesiBox->current_meeting_joined_at)
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">
                Iniziata {{ $onesiBox->current_meeting_joined_at->diffForHumans() }}
            </flux:text>
        @endif
```

- [ ] **Step 4: Verifica passaggio**

Run:
```bash
php artisan test --compact --filter='HeroCardTest'
```
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/dashboard/controls/hero-card.blade.php \
        tests/Feature/Livewire/Dashboard/Controls/HeroCardTest.php
git commit -m "feat(dashboard): render call variant in HeroCard"
```

---

### Task 9: `HeroCard` — variante `offline`

**Files:**
- Modify: `resources/views/livewire/dashboard/controls/hero-card.blade.php`
- Modify: `tests/Feature/Livewire/Dashboard/Controls/HeroCardTest.php`

- [ ] **Step 1: Test che fallisce**

```php
it('renders the offline variant with warning styling and last seen', function () {
    $box = OnesiBox::factory()->offline()->create(['last_seen_at' => now()->subHours(2)]);

    livewire(HeroCard::class, ['onesiBox' => $box, 'state' => 'offline'])
        ->assertSee('Dispositivo offline')
        ->assertSeeHtml('data-hero-state="offline"');
});
```

- [ ] **Step 2: Verifica fail**

```bash
php artisan test --compact --filter='renders the offline variant'
```

- [ ] **Step 3: Estendi la view con il ramo offline e avvolgi il wrapper in una classe condizionale**

Sostituisci il wrapper root del template con:

```blade
<div data-hero-state="{{ $state }}"
     class="rounded-lg border p-4 {{ $state === 'offline' ? 'bg-amber-50 dark:bg-amber-900/20 border-amber-200 dark:border-amber-800' : 'bg-white dark:bg-zinc-800 border-zinc-200 dark:border-zinc-700' }}"
     aria-live="polite">
```

E aggiungi il ramo `offline`:

```blade
    @elseif($state === 'offline')
        <div class="flex items-center gap-2 text-amber-700 dark:text-amber-300">
            <flux:icon name="exclamation-triangle" class="h-5 w-5" />
            <flux:text class="font-medium">Dispositivo offline</flux:text>
        </div>
        <flux:text class="mt-2 text-xs text-zinc-500 dark:text-zinc-400">
            Ultimo contatto: {{ $onesiBox->last_seen_at?->diffForHumans() ?? '—' }}
        </flux:text>
        @if($onesiBox->app_version)
            <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">v{{ $onesiBox->app_version }}</flux:text>
        @endif
```

- [ ] **Step 4: Verifica passaggio**

```bash
php artisan test --compact --filter='HeroCardTest'
```
Expected: tutti PASS.

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/dashboard/controls/hero-card.blade.php \
        tests/Feature/Livewire/Dashboard/Controls/HeroCardTest.php
git commit -m "feat(dashboard): render offline variant in HeroCard"
```

---

### Task 10: `HeroCard` — azioni pause/resume/stop/leaveZoom

**Files:**
- Modify: `app/Livewire/Dashboard/Controls/HeroCard.php`
- Modify: `resources/views/livewire/dashboard/controls/hero-card.blade.php`
- Modify: `tests/Feature/Livewire/Dashboard/Controls/HeroCardTest.php`

- [ ] **Step 1: Test che falliscono**

Aggiungi a `HeroCardTest.php`:

```php
use App\Services\OnesiBoxCommandServiceInterface;

it('pause() dispatches a Pause command when media is playing and not paused', function () {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/x.mp3',
        'current_media_type' => 'audio',
    ]);

    $service = Mockery::mock(OnesiBoxCommandServiceInterface::class);
    $service->shouldReceive('sendPauseCommand')->once()->with(Mockery::on(fn ($b) => $b->is($box)));
    app()->instance(OnesiBoxCommandServiceInterface::class, $service);

    livewire(HeroCard::class, ['onesiBox' => $box, 'state' => 'media', 'isPaused' => false])
        ->call('pause');
});

it('resume() dispatches a Resume command when media is paused', function () {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/x.mp3',
        'current_media_type' => 'audio',
    ]);

    $service = Mockery::mock(OnesiBoxCommandServiceInterface::class);
    $service->shouldReceive('sendResumeCommand')->once();
    app()->instance(OnesiBoxCommandServiceInterface::class, $service);

    livewire(HeroCard::class, ['onesiBox' => $box, 'state' => 'media', 'isPaused' => true])
        ->call('resume');
});

it('stop() dispatches a Stop command on the current media', function () {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Playing,
        'current_media_url' => 'https://example.com/x.mp3',
        'current_media_type' => 'audio',
    ]);

    $service = Mockery::mock(OnesiBoxCommandServiceInterface::class);
    $service->shouldReceive('sendStopCommand')->once();
    app()->instance(OnesiBoxCommandServiceInterface::class, $service);

    livewire(HeroCard::class, ['onesiBox' => $box, 'state' => 'media'])
        ->call('stop');
});

it('leaveZoom() dispatches a LeaveZoom command while on a call', function () {
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '123',
    ]);

    $service = Mockery::mock(OnesiBoxCommandServiceInterface::class);
    $service->shouldReceive('sendLeaveZoomCommand')->once();
    app()->instance(OnesiBoxCommandServiceInterface::class, $service);

    livewire(HeroCard::class, ['onesiBox' => $box, 'state' => 'call'])
        ->call('leaveZoom');
});
```

- [ ] **Step 2: Verifica che falliscano**

```bash
php artisan test --compact --filter='pause\(\)|resume\(\)|stop\(\)|leaveZoom'
```

- [ ] **Step 3: Implementa i metodi**

Modifica `app/Livewire/Dashboard/Controls/HeroCard.php` in due punti:

**3a)** In testa al file, accanto agli altri `use` di namespace, aggiungi:

```php
use App\Concerns\HandlesOnesiBoxErrors;
use App\Services\OnesiBoxCommandServiceInterface;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
```

**3b)** Subito dopo la dichiarazione della classe, aggiungi i due trait:

```php
    use AuthorizesRequests;
    use HandlesOnesiBoxErrors;
```

**3c)** Aggiungi i metodi d'azione dopo la proprietà `$isPaused`:

```php
    public function pause(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendPauseCommand($this->onesiBox),
            successMessage: 'Comando pausa inviato',
        );
    }

    public function resume(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendResumeCommand($this->onesiBox),
            successMessage: 'Comando ripresa inviato',
        );
    }

    public function stop(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendStopCommand($this->onesiBox),
            successMessage: 'Riproduzione interrotta',
        );
    }

    public function leaveZoom(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: fn () => $commandService->sendLeaveZoomCommand($this->onesiBox),
            successMessage: 'Chiamata terminata',
        );
    }
```


- [ ] **Step 4: Aggiungi i pulsanti nella view**

Nel ramo `media`, sotto la progress bar:

```blade
        <div class="mt-4 flex gap-2">
            @if($isPaused)
                <flux:button wire:click="resume" variant="primary" icon="play" class="flex-1">Riprendi</flux:button>
            @else
                <flux:button wire:click="pause" variant="filled" icon="pause" class="flex-1">Pausa</flux:button>
            @endif
            <flux:button wire:click="stop" variant="danger" icon="stop" class="flex-1">Stop</flux:button>
        </div>
```

Nel ramo `call`:

```blade
        <div class="mt-4">
            <flux:button wire:click="leaveZoom" variant="danger" icon="phone-x-mark" class="w-full">Termina chiamata</flux:button>
        </div>
```

- [ ] **Step 5: Verifica passaggio**

```bash
php artisan test --compact --filter='HeroCardTest'
```
Expected: tutti PASS (i test di rendering precedenti devono continuare a passare).

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Dashboard/Controls/HeroCard.php \
        resources/views/livewire/dashboard/controls/hero-card.blade.php \
        tests/Feature/Livewire/Dashboard/Controls/HeroCardTest.php
git commit -m "feat(dashboard): add pause/resume/stop/leaveZoom actions to HeroCard"
```

---

### Task 11: `BottomBar` — scheletro + visibilità

**Files:**
- Create: `app/Livewire/Dashboard/Controls/BottomBar.php`
- Create: `resources/views/livewire/dashboard/controls/bottom-bar.blade.php`
- Create: `tests/Feature/Livewire/Dashboard/Controls/BottomBarTest.php`

- [ ] **Step 1: Genera i file**

```bash
php artisan make:livewire Dashboard/Controls/BottomBar --no-interaction && \
php artisan make:test --pest Livewire/Dashboard/Controls/BottomBarTest --no-interaction
```

- [ ] **Step 2: Test che falliscono**

In `BottomBarTest.php`:

```php
<?php

declare(strict_types=1);

use App\Livewire\Dashboard\Controls\BottomBar;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

it('renders the 4 slots when the user can control and the box is online', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($user, ['permission' => 'full']);

    $this->actingAs($user);

    livewire(BottomBar::class, ['onesiBox' => $box])
        ->assertSeeHtml('data-slot="stop"')
        ->assertSeeHtml('data-slot="volume"')
        ->assertSeeHtml('data-slot="new"')
        ->assertSeeHtml('data-slot="call"');
});

it('renders nothing when the user has only read permission', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($user, ['permission' => 'read']);

    $this->actingAs($user);

    livewire(BottomBar::class, ['onesiBox' => $box])
        ->assertDontSeeHtml('data-slot="stop"')
        ->assertDontSeeHtml('data-slot="volume"');
});
```

- [ ] **Step 3: Verifica che falliscano**

```bash
php artisan test --compact --filter='BottomBarTest'
```

- [ ] **Step 4: Implementa la classe**

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Concerns\ChecksOnesiBoxPermission;
use App\Models\OnesiBox;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

class BottomBar extends Component
{
    use ChecksOnesiBoxPermission;

    #[Locked]
    public OnesiBox $onesiBox;

    #[Computed]
    public function isOnline(): bool
    {
        return $this->onesiBox->isOnline();
    }

    #[Computed]
    public function visible(): bool
    {
        return $this->canControl();
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.bottom-bar');
    }
}
```

- [ ] **Step 5: Implementa la view (solo 4 slot vuoti per ora)**

```blade
@if($this->visible)
    <nav class="fixed inset-x-0 bottom-0 z-40 border-t border-zinc-200 bg-white/95 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95 pb-[env(safe-area-inset-bottom)]"
         aria-label="Azioni rapide">
        <div class="mx-auto flex max-w-4xl items-stretch justify-around px-2 py-2 {{ $this->isOnline ? '' : 'opacity-40 pointer-events-none' }}">
            <button type="button" data-slot="stop" class="flex min-h-14 min-w-14 flex-col items-center justify-center gap-1 rounded-lg text-xs text-red-600 dark:text-red-400" aria-label="Stop tutto"></button>
            <button type="button" data-slot="volume" class="flex min-h-14 min-w-14 flex-col items-center justify-center gap-1 rounded-lg text-xs" aria-label="Volume"></button>
            <button type="button" data-slot="new" class="flex min-h-14 flex-1 flex-col items-center justify-center gap-1 rounded-lg text-xs font-semibold" aria-label="Nuovo contenuto"></button>
            <button type="button" data-slot="call" class="flex min-h-14 min-w-14 flex-col items-center justify-center gap-1 rounded-lg text-xs" aria-label="Chiama"></button>
        </div>
    </nav>
@endif
```

- [ ] **Step 6: Verifica passaggio**

```bash
php artisan test --compact --filter='BottomBarTest'
```

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Dashboard/Controls/BottomBar.php \
        resources/views/livewire/dashboard/controls/bottom-bar.blade.php \
        tests/Feature/Livewire/Dashboard/Controls/BottomBarTest.php
git commit -m "feat(dashboard): add BottomBar skeleton with visibility gating"
```

---

### Task 12: `BottomBar` — slot Stop

**Files:**
- Modify: `app/Livewire/Dashboard/Controls/BottomBar.php`
- Modify: `resources/views/livewire/dashboard/controls/bottom-bar.blade.php`
- Modify: `tests/Feature/Livewire/Dashboard/Controls/BottomBarTest.php`

- [ ] **Step 1: Test che fallisce**

```php
use App\Enums\OnesiBoxStatus;
use App\Services\OnesiBoxCommandServiceInterface;

it('stopAll dispatches Stop when media is playing, and also LeaveZoom if in a call', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_media_url' => 'https://x/y.mp3',
        'current_media_type' => 'audio',
    ]);
    $box->caregivers()->attach($user, ['permission' => 'full']);
    $this->actingAs($user);

    $service = Mockery::mock(OnesiBoxCommandServiceInterface::class);
    $service->shouldReceive('sendStopCommand')->once();
    $service->shouldReceive('sendLeaveZoomCommand')->once();
    app()->instance(OnesiBoxCommandServiceInterface::class, $service);

    livewire(BottomBar::class, ['onesiBox' => $box])->call('stopAll');
});
```

- [ ] **Step 2: Verifica fail**

```bash
php artisan test --compact --filter='stopAll dispatches'
```

- [ ] **Step 3: Implementa metodo**

In `BottomBar.php`:

```php
use App\Enums\OnesiBoxStatus;
use App\Services\OnesiBoxCommandServiceInterface;
use App\Concerns\HandlesOnesiBoxErrors;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
```

```php
    use AuthorizesRequests;
    use HandlesOnesiBoxErrors;

    public function stopAll(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        $this->executeWithErrorHandling(
            callback: function () use ($commandService): void {
                $commandService->sendStopCommand($this->onesiBox);
                if ($this->onesiBox->status === OnesiBoxStatus::Calling) {
                    $commandService->sendLeaveZoomCommand($this->onesiBox);
                }
            },
            successMessage: 'Tutte le riproduzioni sono state interrotte.'
        );
    }
```

- [ ] **Step 4: Aggiorna view (bottone Stop)**

Sostituisci il bottone `data-slot="stop"`:

```blade
<button type="button"
        data-slot="stop"
        wire:click="stopAll"
        class="flex min-h-14 min-w-14 flex-col items-center justify-center gap-1 rounded-lg text-xs text-red-600 dark:text-red-400"
        aria-label="Stop tutto">
    <flux:icon name="stop-circle" class="h-6 w-6" />
    <span>Stop</span>
</button>
```

- [ ] **Step 5: Verifica passaggio**

```bash
php artisan test --compact --filter='BottomBarTest'
```

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Dashboard/Controls/BottomBar.php \
        resources/views/livewire/dashboard/controls/bottom-bar.blade.php \
        tests/Feature/Livewire/Dashboard/Controls/BottomBarTest.php
git commit -m "feat(dashboard): wire Stop slot in BottomBar"
```

---

### Task 13: `BottomBar` — slot Volume (popover che riusa `VolumeControl`)

**Files:**
- Modify: `resources/views/livewire/dashboard/controls/bottom-bar.blade.php`
- Modify: `tests/Feature/Livewire/Dashboard/Controls/BottomBarTest.php`

- [ ] **Step 1: Test che fallisce**

```php
it('renders a popover that mounts the VolumeControl component', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($user, ['permission' => 'full']);
    $this->actingAs($user);

    livewire(BottomBar::class, ['onesiBox' => $box])
        ->assertSeeHtml('flux:popover')
        ->assertSeeHtml('data-slot="volume"');
});
```

*Nota:* l'assert `flux:popover` funziona perché Flux compila i componenti con attributi data che contengono il nome; se falso-positivo, sostituire con `->assertSeeHtml('data-flux-popover')` dopo verifica del markup generato.

- [ ] **Step 2: Verifica fail**

```bash
php artisan test --compact --filter='renders a popover that mounts the VolumeControl'
```

- [ ] **Step 3: Sostituisci il bottone volume con un popover**

Nel template, sostituisci il bottone `data-slot="volume"`:

```blade
<flux:popover data-slot="volume" class="w-72 p-4">
    <x-slot name="trigger">
        <button type="button"
                class="flex min-h-14 min-w-14 flex-col items-center justify-center gap-1 rounded-lg text-xs"
                aria-label="Volume">
            <flux:icon name="speaker-wave" class="h-6 w-6" />
            <span>Volume</span>
        </button>
    </x-slot>

    <livewire:dashboard.controls.volume-control :onesiBox="$onesiBox" wire:key="bottom-volume-{{ $onesiBox->id }}" />
</flux:popover>
```

**Nota:** se l'API Flux del popover nel progetto non usa `x-slot:trigger` ma `slot="trigger"` o uno style diverso, ispeziona altri usi di `flux:popover` nel codebase (es. `grep -r "flux:popover" resources/views`) e uniformati.

- [ ] **Step 4: Verifica passaggio**

```bash
php artisan test --compact --filter='BottomBarTest'
```

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/dashboard/controls/bottom-bar.blade.php \
        tests/Feature/Livewire/Dashboard/Controls/BottomBarTest.php
git commit -m "feat(dashboard): wire Volume slot via popover hosting VolumeControl"
```

---

### Task 14: `BottomBar` — slot Nuovo e slot Chiama

**Files:**
- Modify: `app/Livewire/Dashboard/Controls/BottomBar.php`
- Modify: `resources/views/livewire/dashboard/controls/bottom-bar.blade.php`
- Modify: `tests/Feature/Livewire/Dashboard/Controls/BottomBarTest.php`

Scopo:
- slot **Nuovo** → dispatcha evento Livewire `open-quick-play` verso `QuickPlaySheet`
- slot **Chiama** → se `!isInCall` dispatcha evento `open-quick-play` con parametro `tab=zoom`; se `isInCall` chiama `sendLeaveZoomCommand` diretto.

- [ ] **Step 1: Test che falliscono**

```php
it('openNew() dispatches open-quick-play without a preselected tab', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($user, ['permission' => 'full']);
    $this->actingAs($user);

    livewire(BottomBar::class, ['onesiBox' => $box])
        ->call('openNew')
        ->assertDispatched('open-quick-play');
});

it('call() dispatches open-quick-play with tab=zoom when no active call', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create(['status' => OnesiBoxStatus::Idle]);
    $box->caregivers()->attach($user, ['permission' => 'full']);
    $this->actingAs($user);

    livewire(BottomBar::class, ['onesiBox' => $box])
        ->call('callAction')
        ->assertDispatched('open-quick-play', tab: 'zoom');
});

it('call() ends the current call when in a call', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create([
        'status' => OnesiBoxStatus::Calling,
        'current_meeting_id' => '999',
    ]);
    $box->caregivers()->attach($user, ['permission' => 'full']);
    $this->actingAs($user);

    $service = Mockery::mock(OnesiBoxCommandServiceInterface::class);
    $service->shouldReceive('sendLeaveZoomCommand')->once();
    app()->instance(OnesiBoxCommandServiceInterface::class, $service);

    livewire(BottomBar::class, ['onesiBox' => $box])->call('callAction');
});
```

- [ ] **Step 2: Verifica fail**

```bash
php artisan test --compact --filter='openNew|callAction'
```

- [ ] **Step 3: Implementa metodi**

In `BottomBar.php`:

```php
    public function openNew(): void
    {
        $this->dispatch('open-quick-play');
    }

    public function callAction(OnesiBoxCommandServiceInterface $commandService): void
    {
        $this->authorize('control', $this->onesiBox);

        if ($this->onesiBox->status === OnesiBoxStatus::Calling) {
            $this->executeWithErrorHandling(
                callback: fn () => $commandService->sendLeaveZoomCommand($this->onesiBox),
                successMessage: 'Chiamata terminata',
            );
            return;
        }

        $this->dispatch('open-quick-play', tab: 'zoom');
    }
```

- [ ] **Step 4: Aggiorna view (sostituisci i due bottoni `data-slot="new"` e `data-slot="call"`)**

```blade
<button type="button"
        data-slot="new"
        wire:click="openNew"
        class="flex min-h-14 flex-1 flex-col items-center justify-center gap-1 rounded-lg bg-indigo-600 text-xs font-semibold text-white dark:bg-indigo-500"
        aria-label="Nuovo contenuto">
    <flux:icon name="plus-circle" class="h-6 w-6" />
    <span>Nuovo</span>
</button>

<button type="button"
        data-slot="call"
        wire:click="callAction"
        class="flex min-h-14 min-w-14 flex-col items-center justify-center gap-1 rounded-lg text-xs {{ $onesiBox->status->value === 'calling' ? 'text-red-600 dark:text-red-400' : '' }}"
        aria-label="{{ $onesiBox->status->value === 'calling' ? 'Termina chiamata' : 'Avvia chiamata' }}">
    <flux:icon name="{{ $onesiBox->status->value === 'calling' ? 'phone-x-mark' : 'phone' }}" class="h-6 w-6" />
    <span>{{ $onesiBox->status->value === 'calling' ? 'Termina' : 'Chiama' }}</span>
</button>
```

**Nota:** se `$onesiBox->status` non è un backed-enum con `value` stringa, sostituire con un confronto nativo (`=== OnesiBoxStatus::Calling`).

- [ ] **Step 5: Verifica passaggio**

```bash
php artisan test --compact --filter='BottomBarTest'
```

- [ ] **Step 6: Commit**

```bash
git add app/Livewire/Dashboard/Controls/BottomBar.php \
        resources/views/livewire/dashboard/controls/bottom-bar.blade.php \
        tests/Feature/Livewire/Dashboard/Controls/BottomBarTest.php
git commit -m "feat(dashboard): wire New and Call slots in BottomBar"
```

---

### Task 15: `QuickPlaySheet` — scheletro con menu iniziale

**Files:**
- Create: `app/Livewire/Dashboard/Controls/QuickPlaySheet.php`
- Create: `resources/views/livewire/dashboard/controls/quick-play-sheet.blade.php`
- Create: `tests/Feature/Livewire/Dashboard/Controls/QuickPlaySheetTest.php`

- [ ] **Step 1: Genera i file**

```bash
php artisan make:livewire Dashboard/Controls/QuickPlaySheet --no-interaction && \
php artisan make:test --pest Livewire/Dashboard/Controls/QuickPlaySheetTest --no-interaction
```

- [ ] **Step 2: Test che falliscono**

In `QuickPlaySheetTest.php`:

```php
<?php

declare(strict_types=1);

use App\Livewire\Dashboard\Controls\QuickPlaySheet;
use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use function Pest\Livewire\livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->user = User::factory()->create();
    $this->actingAs($this->user);
});

it('starts closed with no active tab', function () {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    livewire(QuickPlaySheet::class, ['onesiBox' => $box])
        ->assertSet('open', false)
        ->assertSet('tab', null);
});

it('opens and shows the initial menu when receiving open-quick-play', function () {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    livewire(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play')
        ->assertSet('open', true)
        ->assertSet('tab', null)
        ->assertSee('Cosa vuoi riprodurre?');
});

it('preselects a tab when open-quick-play carries a tab parameter', function () {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    livewire(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play', tab: 'zoom')
        ->assertSet('open', true)
        ->assertSet('tab', 'zoom');
});

it('close() resets state', function () {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    livewire(QuickPlaySheet::class, ['onesiBox' => $box])
        ->set('open', true)
        ->set('tab', 'audio')
        ->call('close')
        ->assertSet('open', false)
        ->assertSet('tab', null);
});
```

- [ ] **Step 3: Verifica che falliscano**

```bash
php artisan test --compact --filter='QuickPlaySheetTest'
```

- [ ] **Step 4: Implementa la classe**

```php
<?php

declare(strict_types=1);

namespace App\Livewire\Dashboard\Controls;

use App\Models\OnesiBox;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

class QuickPlaySheet extends Component
{
    #[Locked]
    public OnesiBox $onesiBox;

    public bool $open = false;

    /** @var null|'audio'|'video'|'stream'|'playlists'|'zoom' */
    public ?string $tab = null;

    #[On('open-quick-play')]
    public function openSheet(?string $tab = null): void
    {
        $this->open = true;
        $this->tab = $tab;
    }

    public function selectTab(string $tab): void
    {
        $this->tab = $tab;
    }

    public function back(): void
    {
        $this->tab = null;
    }

    public function close(): void
    {
        $this->open = false;
        $this->tab = null;
    }

    public function render(): View
    {
        return view('livewire.dashboard.controls.quick-play-sheet');
    }
}
```

- [ ] **Step 5: Implementa la view (menu iniziale)**

```blade
<div>
    <flux:modal wire:model="open" variant="flyout" position="bottom">
        @if($tab === null)
            <flux:heading size="lg">Cosa vuoi riprodurre?</flux:heading>
            <div class="mt-4 flex flex-col gap-2">
                <flux:button wire:click="selectTab('audio')" variant="ghost" icon="musical-note" class="justify-start">Audio da URL</flux:button>
                <flux:button wire:click="selectTab('video')" variant="ghost" icon="video-camera" class="justify-start">Video da URL</flux:button>
                <flux:button wire:click="selectTab('stream')" variant="ghost" icon="play" class="justify-start">Stream YouTube</flux:button>
                <flux:button wire:click="selectTab('playlists')" variant="ghost" icon="queue-list" class="justify-start">Dalle playlist salvate</flux:button>
                <flux:button wire:click="selectTab('zoom')" variant="ghost" icon="phone" class="justify-start">Avvia chiamata Zoom</flux:button>
            </div>
        @else
            <flux:button wire:click="back" variant="subtle" icon="arrow-left" size="sm">Indietro</flux:button>
            <div class="mt-4">
                {{-- I sub-form verranno montati nelle task successive --}}
                <flux:text class="text-sm text-zinc-500">Tab: {{ $tab }}</flux:text>
            </div>
        @endif
    </flux:modal>
</div>
```

**Nota:** verifica l'API corretta di `flux:modal` in questa versione di Flux Free (potrebbe essere `<flux:modal variant="flyout" name="…">`); ispeziona un uso esistente nel progetto (`grep -rn "flux:modal" resources/views`) e adegua se necessario.

- [ ] **Step 6: Verifica passaggio**

```bash
php artisan test --compact --filter='QuickPlaySheetTest'
```

- [ ] **Step 7: Commit**

```bash
git add app/Livewire/Dashboard/Controls/QuickPlaySheet.php \
        resources/views/livewire/dashboard/controls/quick-play-sheet.blade.php \
        tests/Feature/Livewire/Dashboard/Controls/QuickPlaySheetTest.php
git commit -m "feat(dashboard): add QuickPlaySheet skeleton with initial menu"
```

---

### Task 16: `QuickPlaySheet` — tab Audio (riusa `AudioPlayer`)

**Files:**
- Modify: `resources/views/livewire/dashboard/controls/quick-play-sheet.blade.php`
- Modify: `tests/Feature/Livewire/Dashboard/Controls/QuickPlaySheetTest.php`

- [ ] **Step 1: Test che fallisce**

```php
it('mounts AudioPlayer inside the sheet when tab=audio', function () {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    livewire(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play', tab: 'audio')
        ->assertSeeHtml('wire:id')  // Livewire child mount sentinel
        ->assertSee('Audio'); // assumes AudioPlayer view contains the word "Audio" or similar; adjust after inspection
});
```

**Nota:** adegua l'assertion `assertSee('Audio')` dopo aver controllato il testo effettivo renderizzato da `AudioPlayer`. Scopo: verificare che il child component sia effettivamente mounted (non solo un placeholder).

- [ ] **Step 2: Verifica fail**

```bash
php artisan test --compact --filter='mounts AudioPlayer'
```

- [ ] **Step 3: Estendi la view**

Nel ramo `@else`, sostituisci il placeholder con uno switch:

```blade
        @else
            <flux:button wire:click="back" variant="subtle" icon="arrow-left" size="sm">Indietro</flux:button>
            <div class="mt-4">
                @switch($tab)
                    @case('audio')
                        <livewire:dashboard.controls.audio-player :onesiBox="$onesiBox" wire:key="qps-audio-{{ $onesiBox->id }}" />
                        @break
                    @default
                        <flux:text class="text-sm text-zinc-500">Tab: {{ $tab }}</flux:text>
                @endswitch
            </div>
        @endif
```

- [ ] **Step 4: Verifica passaggio**

```bash
php artisan test --compact --filter='QuickPlaySheetTest'
```

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/dashboard/controls/quick-play-sheet.blade.php \
        tests/Feature/Livewire/Dashboard/Controls/QuickPlaySheetTest.php
git commit -m "feat(dashboard): mount AudioPlayer in QuickPlaySheet audio tab"
```

---

### Task 17: `QuickPlaySheet` — tab Video + Stream + Zoom

**Files:**
- Modify: `resources/views/livewire/dashboard/controls/quick-play-sheet.blade.php`
- Modify: `tests/Feature/Livewire/Dashboard/Controls/QuickPlaySheetTest.php`

- [ ] **Step 1: Test che falliscono (tre test analoghi a quello di audio, uno per tab)**

```php
it('mounts VideoPlayer when tab=video', function () {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    livewire(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play', tab: 'video')
        ->assertSee('Video'); // adjust after inspection of VideoPlayer view copy
});

it('mounts StreamPlayer when tab=stream', function () {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    livewire(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play', tab: 'stream')
        ->assertSee('Stream'); // adjust
});

it('mounts ZoomCall when tab=zoom', function () {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    livewire(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play', tab: 'zoom')
        ->assertSee('Zoom'); // adjust
});
```

- [ ] **Step 2: Verifica fail**

```bash
php artisan test --compact --filter='QuickPlaySheetTest'
```

- [ ] **Step 3: Estendi lo switch nella view**

```blade
                    @case('video')
                        <livewire:dashboard.controls.video-player :onesiBox="$onesiBox" wire:key="qps-video-{{ $onesiBox->id }}" />
                        @break
                    @case('stream')
                        <livewire:dashboard.controls.stream-player :onesiBox="$onesiBox" wire:key="qps-stream-{{ $onesiBox->id }}" />
                        @break
                    @case('zoom')
                        <livewire:dashboard.controls.zoom-call :onesiBox="$onesiBox" wire:key="qps-zoom-{{ $onesiBox->id }}" />
                        @break
```

- [ ] **Step 4: Verifica passaggio**

```bash
php artisan test --compact --filter='QuickPlaySheetTest'
```

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/dashboard/controls/quick-play-sheet.blade.php \
        tests/Feature/Livewire/Dashboard/Controls/QuickPlaySheetTest.php
git commit -m "feat(dashboard): mount Video/Stream/Zoom players in QuickPlaySheet tabs"
```

---

### Task 18: `QuickPlaySheet` — tab Playlist salvate

**Files:**
- Modify: `resources/views/livewire/dashboard/controls/quick-play-sheet.blade.php`
- Modify: `tests/Feature/Livewire/Dashboard/Controls/QuickPlaySheetTest.php`

- [ ] **Step 1: Test che fallisce**

```php
it('mounts SavedPlaylists when tab=playlists', function () {
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($this->user, ['permission' => 'full']);

    livewire(QuickPlaySheet::class, ['onesiBox' => $box])
        ->dispatch('open-quick-play', tab: 'playlists')
        ->assertSee('playlist'); // case-insensitive substring; adjust after inspecting SavedPlaylists view
});
```

- [ ] **Step 2: Verifica fail**

```bash
php artisan test --compact --filter='mounts SavedPlaylists'
```

- [ ] **Step 3: Estendi lo switch**

```blade
                    @case('playlists')
                        <livewire:dashboard.controls.saved-playlists :onesiBox="$onesiBox" wire:key="qps-playlists-{{ $onesiBox->id }}" />
                        @break
```

- [ ] **Step 4: Verifica passaggio**

```bash
php artisan test --compact --filter='QuickPlaySheetTest'
```

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/dashboard/controls/quick-play-sheet.blade.php \
        tests/Feature/Livewire/Dashboard/Controls/QuickPlaySheetTest.php
git commit -m "feat(dashboard): mount SavedPlaylists in QuickPlaySheet playlists tab"
```

---

### Task 19: Refactor di `onesi-box-detail.blade.php`

**Files:**
- Modify: `resources/views/livewire/dashboard/onesi-box-detail.blade.php`
- (verificare) Modify: `app/Livewire/Dashboard/OnesiBoxDetail.php` se servisse esporre `accordionDefaults` come property pubblica al render.

Scopo: sostituire l'intera struttura della view con il layout del design (header sticky + HeroCard + accordion body + BottomBar + QuickPlaySheet).

- [ ] **Step 1: Sovrascrivi `resources/views/livewire/dashboard/onesi-box-detail.blade.php`**

```blade
<div class="mx-auto max-w-4xl pb-24" wire:poll.15s="refreshFromDatabase">
    {{-- Sticky header --}}
    <header class="sticky top-0 z-30 -mx-4 mb-4 border-b border-zinc-200 bg-white/95 px-4 py-3 backdrop-blur dark:border-zinc-700 dark:bg-zinc-900/95 sm:-mx-6 sm:px-6">
        <div class="flex items-center gap-3">
            <flux:button variant="subtle" wire:click="goBack" icon="arrow-left" size="sm" aria-label="Torna alla lista" />

            <div class="min-w-0 flex-1">
                <flux:heading class="truncate text-base font-semibold">{{ $onesiBox->name }}</flux:heading>
                @if($onesiBox->app_version)
                    <flux:text class="text-xs text-zinc-500 dark:text-zinc-400">v{{ $onesiBox->app_version }}</flux:text>
                @endif
            </div>

            <div class="flex items-center gap-2">
                @if($this->isOnline)
                    <span class="relative flex h-2.5 w-2.5" role="status" aria-label="Online">
                        <span class="absolute inline-flex h-full w-full animate-ping rounded-full bg-green-400 opacity-75"></span>
                        <span class="relative inline-flex h-2.5 w-2.5 rounded-full bg-green-500"></span>
                    </span>
                @else
                    <span class="h-2.5 w-2.5 rounded-full bg-zinc-400" role="status" aria-label="Offline"></span>
                @endif
            </div>
        </div>
    </header>

    <div class="px-4 sm:px-6">
        @if($onesiBox->status === \App\Enums\OnesiBoxStatus::Error)
            <flux:callout variant="danger" icon="exclamation-triangle" class="mb-4">
                <flux:callout.heading>Dispositivo in stato di errore</flux:callout.heading>
                <flux:callout.text>
                    L'OnesiBox ha segnalato un errore. Controlla i log in fondo alla pagina.
                </flux:callout.text>
            </flux:callout>
        @endif

        {{-- Hero --}}
        <livewire:dashboard.controls.hero-card
            :onesiBox="$onesiBox"
            :state="$this->heroState"
            :isPaused="$this->isMediaPaused"
            wire:key="hero-{{ $onesiBox->id }}" />

        @if(! $this->recipient)
            <flux:callout variant="warning" icon="exclamation-triangle" class="mt-4">
                <flux:callout.heading>Nessun destinatario associato</flux:callout.heading>
                <flux:callout.text>
                    Questa OnesiBox non ha ancora un destinatario associato. Contatta l'amministratore.
                </flux:callout.text>
            </flux:callout>
        @endif

        {{-- Accordion body --}}
        <div class="mt-4 space-y-2">
            <flux:accordion :default="$this->accordionDefaults">
                @if($this->canControl && $this->isOnline)
                    <flux:accordion.item name="session">
                        <flux:accordion.heading>Sessione in corso</flux:accordion.heading>
                        <flux:accordion.content>
                            <livewire:dashboard.controls.session-status :onesiBox="$onesiBox" wire:key="session-status-{{ $onesiBox->id }}" />
                            <div class="mt-3">
                                <livewire:dashboard.controls.session-manager :onesiBox="$onesiBox" wire:key="session-manager-{{ $onesiBox->id }}" />
                            </div>
                        </flux:accordion.content>
                    </flux:accordion.item>

                    <flux:accordion.item name="commands">
                        <flux:accordion.heading>Comandi in coda</flux:accordion.heading>
                        <flux:accordion.content>
                            <livewire:dashboard.controls.command-queue :onesiBox="$onesiBox" wire:key="command-queue-{{ $onesiBox->id }}" />
                        </flux:accordion.content>
                    </flux:accordion.item>

                    <flux:accordion.item name="meetings">
                        <flux:accordion.heading>Meeting programmati</flux:accordion.heading>
                        <flux:accordion.content>
                            <livewire:dashboard.controls.meeting-schedule :onesi-box="$onesiBox" wire:key="meeting-schedule-{{ $onesiBox->id }}" />
                        </flux:accordion.content>
                    </flux:accordion.item>

                    <flux:accordion.item name="playlists">
                        <flux:accordion.heading>Playlist salvate</flux:accordion.heading>
                        <flux:accordion.content>
                            <livewire:dashboard.controls.saved-playlists :onesiBox="$onesiBox" wire:key="saved-playlists-{{ $onesiBox->id }}" />
                            <div class="mt-4">
                                <livewire:dashboard.controls.playlist-builder :onesiBox="$onesiBox" wire:key="playlist-builder-{{ $onesiBox->id }}" />
                            </div>
                        </flux:accordion.content>
                    </flux:accordion.item>
                @endif

                @if($this->recipient)
                    <flux:accordion.item name="contacts">
                        <flux:accordion.heading>Contatti destinatario</flux:accordion.heading>
                        <flux:accordion.content>
                            @include('livewire.dashboard.partials.recipient-contacts', ['recipient' => $this->recipient])
                        </flux:accordion.content>
                    </flux:accordion.item>
                @endif
            </flux:accordion>

            @if($this->isAdmin)
                <div class="mt-6 border-t border-zinc-200 pt-4 dark:border-zinc-700">
                    <flux:heading size="sm" class="mb-2 flex items-center gap-2 text-zinc-500 dark:text-zinc-400">
                        <flux:icon name="shield-check" class="h-4 w-4" />
                        Amministrazione
                    </flux:heading>

                    <flux:accordion>
                        <flux:accordion.item name="system">
                            <flux:accordion.heading>Sistema</flux:accordion.heading>
                            <flux:accordion.content>
                                <livewire:dashboard.controls.system-info :onesiBox="$onesiBox" wire:key="system-info-{{ $onesiBox->id }}" />
                            </flux:accordion.content>
                        </flux:accordion.item>

                        <flux:accordion.item name="network">
                            <flux:accordion.heading>Rete</flux:accordion.heading>
                            <flux:accordion.content>
                                <livewire:dashboard.controls.network-info :onesiBox="$onesiBox" wire:key="network-info-{{ $onesiBox->id }}" />
                            </flux:accordion.content>
                        </flux:accordion.item>

                        @if($this->isOnline)
                            <flux:accordion.item name="system-controls">
                                <flux:accordion.heading>Controlli sistema</flux:accordion.heading>
                                <flux:accordion.content>
                                    <livewire:dashboard.controls.system-controls :onesiBox="$onesiBox" wire:key="system-{{ $onesiBox->id }}" />
                                </flux:accordion.content>
                            </flux:accordion.item>

                            <flux:accordion.item name="logs">
                                <flux:accordion.heading>Log</flux:accordion.heading>
                                <flux:accordion.content>
                                    <livewire:dashboard.controls.log-viewer :onesiBox="$onesiBox" wire:key="logs-{{ $onesiBox->id }}" />
                                </flux:accordion.content>
                            </flux:accordion.item>
                        @endif
                    </flux:accordion>
                </div>
            @endif
        </div>
    </div>

    {{-- Bottom bar + Quick play sheet --}}
    <livewire:dashboard.controls.bottom-bar :onesiBox="$onesiBox" wire:key="bottom-bar-{{ $onesiBox->id }}" />
    <livewire:dashboard.controls.quick-play-sheet :onesiBox="$onesiBox" wire:key="quick-play-sheet-{{ $onesiBox->id }}" />
</div>
```

- [ ] **Step 2: Crea la partial per i contatti**

Crea `resources/views/livewire/dashboard/partials/recipient-contacts.blade.php`:

```blade
<div class="space-y-3">
    <div class="flex items-center gap-3">
        <flux:icon name="user" class="h-5 w-5 text-zinc-400" />
        <flux:text>{{ $recipient->full_name }}</flux:text>
    </div>

    @if($recipient->phone)
        <div class="flex items-center gap-3">
            <flux:icon name="phone" class="h-5 w-5 text-zinc-400" />
            <a href="tel:{{ $recipient->phone }}" class="text-blue-600 hover:underline dark:text-blue-400">
                {{ $recipient->phone }}
            </a>
        </div>
    @endif

    @if($recipient->full_address)
        <div class="flex items-center gap-3">
            <flux:icon name="map-pin" class="h-5 w-5 text-zinc-400" />
            <flux:text>{{ $recipient->full_address }}</flux:text>
        </div>
    @endif

    @if($recipient->emergency_contacts && count($recipient->emergency_contacts) > 0)
        <div class="mt-4 border-t border-zinc-200 pt-4 dark:border-zinc-700">
            <flux:text class="mb-2 text-sm font-semibold text-zinc-600 dark:text-zinc-300">
                Contatti di emergenza
            </flux:text>
            @foreach($recipient->emergency_contacts as $contact)
                <div class="mb-2 flex items-center gap-3">
                    <flux:icon name="exclamation-triangle" class="h-5 w-5 text-amber-500" />
                    <flux:text>
                        {{ $contact['name'] }}
                        @if(isset($contact['relationship'])) ({{ $contact['relationship'] }}) @endif
                        - {{ $contact['phone'] }}
                    </flux:text>
                </div>
            @endforeach
        </div>
    @endif
</div>
```

**Nota importante su `flux:accordion`:** l'API di `flux:accordion` in Flux UI Free v2 può richiedere adattamenti. Verifica con `grep -rn "flux:accordion" resources/views` per vedere usi esistenti. Se `:default="$this->accordionDefaults"` non è supportato, fallback: renderizzare ogni `<flux:accordion.item>` con un attributo `:open` condizionale sul singolo key, es. `:expanded="$this->accordionDefaults['session'] ?? false"`. Se `flux:accordion` non esiste nella versione installata, usa un semplice wrapper `<details>` HTML nativo con `<summary>` e conserva lo stato default-open via attributo `open`.

- [ ] **Step 3: Esegui tutti i test Livewire della dashboard per regressioni**

```bash
php artisan test --compact --filter='Dashboard'
```
Expected: tutti PASS. Se un test DOM esistente si rompe (es. vecchio `assertSee('Torna alla lista')`), procedi al Task 20 per adeguarlo.

- [ ] **Step 4: Verifica visivamente con Herd (modalità DevTools mobile)**

Apri `https://onesiforo.test/dashboard/1`, simula iPhone 12 (390×844), verifica:
- Header sticky non copre la hero allo scroll.
- Bottom bar sempre visibile, safe-area corretta.
- Accordion "Sessione in corso" aperto se hai una sessione attiva (puoi creare seed via `php artisan tinker --execute 'App\Models\PlaybackSession::factory()->create(["onesi_box_id" => 1, "status" => App\Enums\PlaybackSessionStatus::Active])'`).

- [ ] **Step 5: Commit**

```bash
git add resources/views/livewire/dashboard/onesi-box-detail.blade.php \
        resources/views/livewire/dashboard/partials/recipient-contacts.blade.php
git commit -m "feat(dashboard): restructure onesi-box-detail view with hero + accordion + bottom bar"
```

---

### Task 20: Adeguare il test `OnesiBoxDetailTest` esistente

**Files:**
- Modify: `tests/Feature/Livewire/Dashboard/OnesiBoxDetailTest.php`

Scopo: allineare i test esistenti (che asseriscono la vecchia struttura) alla nuova. Se il file non esisteva prima di Task 4, questo task può essere minimale.

- [ ] **Step 1: Scorri tutti i test del file e aggiorna le assertion DOM**

Esegui:

```bash
php artisan test --compact --filter='OnesiBoxDetailTest'
```

Se ci sono test rossi a causa del redesign:
- sostituisci `assertSee('Contatti Destinatario')` con `assertSee('Contatti destinatario')` (nuovo casing) o aggancia alla partial.
- sostituisci `assertSee('Controlli')` con asserzioni mirate: es. `assertSeeLivewire(\App\Livewire\Dashboard\Controls\BottomBar::class)`.
- sostituisci qualunque assertion legata allo status-row in header con `assertSeeHtml('role="status"')`.

- [ ] **Step 2: Aggiungi un test di integrazione high-level**

```php
it('mounts HeroCard, BottomBar and QuickPlaySheet in the detail view', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($user, ['permission' => 'full']);
    $this->actingAs($user);

    livewire(OnesiBoxDetail::class, ['onesiBox' => $box])
        ->assertSeeLivewire(\App\Livewire\Dashboard\Controls\HeroCard::class)
        ->assertSeeLivewire(\App\Livewire\Dashboard\Controls\BottomBar::class)
        ->assertSeeLivewire(\App\Livewire\Dashboard\Controls\QuickPlaySheet::class);
});
```

- [ ] **Step 3: Verifica passaggio**

```bash
php artisan test --compact --filter='OnesiBoxDetailTest'
```

- [ ] **Step 4: Commit**

```bash
git add tests/Feature/Livewire/Dashboard/OnesiBoxDetailTest.php
git commit -m "test(dashboard): align OnesiBoxDetail tests with mobile redesign"
```

---

### Task 21: Browser smoke test mobile

**Files:**
- Create: `tests/Browser/DashboardDetailMobileTest.php`

- [ ] **Step 1: Crea il test browser**

Run:
```bash
php artisan make:test --pest Browser/DashboardDetailMobileTest --no-interaction
```

- [ ] **Step 2: Scrivi il test**

```php
<?php

declare(strict_types=1);

use App\Models\OnesiBox;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the mobile dashboard detail page without JS errors at 390x844', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create(['name' => 'Test Box']);
    $box->caregivers()->attach($user, ['permission' => 'full']);

    $page = visit(route('dashboard.show', $box))
        ->actingAs($user)
        ->resize(390, 844);

    $page->assertSee('Test Box')
        ->assertNoJavascriptErrors()
        ->assertNoConsoleLogs('error');
});

it('opens the quick play sheet when tapping the New button', function () {
    $user = User::factory()->create();
    $box = OnesiBox::factory()->online()->create();
    $box->caregivers()->attach($user, ['permission' => 'full']);

    visit(route('dashboard.show', $box))
        ->actingAs($user)
        ->resize(390, 844)
        ->click('[data-slot="new"]')
        ->assertSee('Cosa vuoi riprodurre?');
});
```

**Nota:** l'API Pest 4 Browser (`visit`, `assertNoJavascriptErrors`) è quella documentata; se la versione installata usa `$browser->visit(...)` style (Duskfull), adegua. Verifica prima con `php artisan test --list-tests tests/Browser` o leggendo un altro test in `tests/Browser/`.

- [ ] **Step 3: Esegui**

```bash
php artisan test --compact --filter='DashboardDetailMobileTest'
```
Expected: PASS.

- [ ] **Step 4: Commit**

```bash
git add tests/Browser/DashboardDetailMobileTest.php
git commit -m "test(browser): add mobile viewport smoke test for dashboard detail"
```

---

### Task 22: Pint, PHPStan, test suite completa

**Files:** tutti i file toccati.

- [ ] **Step 1: Pint**

```bash
vendor/bin/pint --dirty --format agent
```
Expected: le modifiche vengono auto-fixate (ordinamento import, ecc.); nessun errore.

- [ ] **Step 2: PHPStan**

```bash
vendor/bin/phpstan analyse --memory-limit=2G
```
Expected: zero *nuovi* errori rispetto alla baseline. Se ce ne sono di pre-esistenti non correlati, lasciarli.

- [ ] **Step 3: Test suite completa**

```bash
php artisan test --compact
```
Expected: tutti verdi.

- [ ] **Step 4: Commit fissazioni stile se Pint ne ha prodotte**

```bash
git add -A
git diff --cached --quiet || git commit -m "chore: apply Pint style fixes"
```

---

### Task 23: Aprire la pull request

**Files:** nessuno.

- [ ] **Step 1: Push del branch**

```bash
git push -u origin feat/dashboard-detail-mobile-redesign
```

- [ ] **Step 2: Cattura screenshot "after" per il PR**

Attraverso Herd:
1. Apri `https://onesiforo.test/dashboard/1` in viewport iPhone (DevTools responsive mode 390×844) sia in stato idle che con un media in riproduzione (seed con `php artisan tinker --execute '...'`).
2. Salva 2 screenshot `dashboard-detail-mobile-after-idle.png` e `dashboard-detail-mobile-after-media.png` in `docs/superpowers/specs/screenshots/`.

- [ ] **Step 3: Apri la PR con `gh pr create`**

```bash
gh pr create --title "feat(dashboard): mobile-first redesign of OnesiBox detail page" --body "$(cat <<'EOF'
## Summary
- Ristruttura mobile-first la pagina `/dashboard/{onesiBox}` con header sticky, HeroCard dinamica (idle/media/call/offline), corpo ad accordion e bottom bar sticky (Stop/Volume/Nuovo/Chiama).
- Aggiunge `sendPauseCommand` e `sendResumeCommand` a `OnesiBoxCommandServiceInterface` / `OnesiBoxCommandService` mappate su `CommandType::PauseMedia` / `ResumeMedia`.
- Introduce 3 nuovi componenti Livewire (`HeroCard`, `BottomBar`, `QuickPlaySheet`) riusando tutti i componenti esistenti dentro accordion.

Spec: `docs/superpowers/specs/2026-04-22-dashboard-detail-mobile-redesign-design.md`

## Test plan
- [ ] Tutti i test passano (`php artisan test --compact`)
- [ ] Pint pulito (`vendor/bin/pint --test --format agent`)
- [ ] PHPStan pulito (`vendor/bin/phpstan analyse`)
- [ ] Verifica manuale viewport iPhone 12/13 Safari (390×844): stato idle
- [ ] Verifica manuale viewport: media in riproduzione con progress bar
- [ ] Verifica manuale viewport: Zoom call in corso + tap "Termina"
- [ ] Verifica manuale viewport: offline + bottom bar ghost
- [ ] Verifica permessi: caregiver read-only non vede la bottom bar
- [ ] Verifica Dark mode
EOF
)"
```

- [ ] **Step 4: Output atteso**

URL della PR stampato a terminale.

---

## Self-Review checklist

Dopo aver eseguito tutti i task, conferma quanto segue prima di chiedere la review umana:

- [ ] **Spec coverage:**
  - Header sticky + back + stato online + versione → Task 19.
  - 4 varianti hero (idle/media/call/offline) → Task 6–10.
  - Progress bar condizionale su position/duration → Task 7.
  - Bottom bar con 4 slot + popover volume → Task 11–14.
  - Bottom sheet "Riproduci…" con 5 tab → Task 15–18.
  - Accordion body con default-open condizionali → Task 5, Task 19.
  - Sezione Amministrazione gated `isAdmin` → Task 19.
  - Nuovi `CommandType` NON servono (PauseMedia/ResumeMedia già esistono); aggiunti solo i metodi service → Task 2–3.
  - Stati edge (`!recipient`, read-only, offline) → Task 11 (visibilità), Task 19 (callout warning per recipient mancante).
  - `OnesiBoxStatus::Error` banner dedicato sopra la hero → Task 19.
  - Accessibilità (`aria-label`, `aria-live`, tap target 44/56) → Task 6, Task 11, Task 19.
  - Dark mode → Task 6–19.
- [ ] **Placeholder scan:** nessun "TBD", "TODO", "implement later".
- [ ] **Type consistency:** `heroState` signature coerente tra `OnesiBoxDetail` (string computed) e `HeroCard` (public string `$state`). I nomi metodi service (`sendPauseCommand`, `sendResumeCommand`, `sendStopCommand`, `sendLeaveZoomCommand`, `sendVolumeCommand`) coerenti lungo tutto il plan.
