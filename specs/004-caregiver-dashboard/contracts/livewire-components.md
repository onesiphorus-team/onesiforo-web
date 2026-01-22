# Livewire Components Contract

**Feature**: 004-caregiver-dashboard
**Date**: 2026-01-22

## Component: Dashboard\OnesiBoxList

**Route**: `/dashboard`
**Purpose**: Visualizza lista OnesiBox assegnate al caregiver autenticato

### Public Properties

| Property | Type | Description |
|----------|------|-------------|
| — | — | Stateless, dati via computed |

### Computed Properties

| Property | Return Type | Description |
|----------|-------------|-------------|
| `onesiBoxes` | `Collection<OnesiBox>` | OnesiBox dell'utente con recipient e pivot |

### Actions

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `selectOnesiBox` | `int $id` | `void` | Redirect a dettaglio |

### Events Listened

| Event | Handler | Description |
|-------|---------|-------------|
| `echo-private:user.{userId},OnesiBoxStatusUpdated` | `$refresh` | Aggiorna lista quando cambia stato |

### Blade Template Structure

```blade
<div wire:poll.10s.visible>
    @forelse($this->onesiBoxes as $box)
        <flux:card wire:key="box-{{ $box->id }}">
            <!-- Nome, stato online, stato attività -->
            <flux:button wire:click="selectOnesiBox({{ $box->id }})">
        </flux:card>
    @empty
        <flux:callout>Nessuna OnesiBox assegnata</flux:callout>
    @endforelse
</div>
```

---

## Component: Dashboard\OnesiBoxDetail

**Route**: `/dashboard/{onesiBox}`
**Purpose**: Mostra dettaglio OnesiBox e contatti recipient

### Public Properties

| Property | Type | Description |
|----------|------|-------------|
| `onesiBox` | `OnesiBox` | Model binding da route |

### Computed Properties

| Property | Return Type | Description |
|----------|-------------|-------------|
| `recipient` | `?Recipient` | Recipient associato |
| `permission` | `OnesiBoxPermission` | Permesso utente corrente |
| `canControl` | `bool` | True se permission = Full |
| `isOnline` | `bool` | Stato connessione |

### Actions

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `goBack` | — | `void` | Torna alla lista |

### Events Listened

| Event | Handler | Description |
|-------|---------|-------------|
| `echo-private:onesibox.{onesiBox.id},StatusUpdated` | `refreshStatus` | Aggiorna stato real-time |

### Authorization

```php
public function mount(OnesiBox $onesiBox): void
{
    $this->authorize('view', $onesiBox);
}
```

### Blade Template Structure

```blade
<div>
    <!-- Header: nome, stato, ultimo heartbeat -->
    <section>
        <flux:heading>{{ $onesiBox->name }}</flux:heading>
        <flux:badge :color="$this->isOnline ? 'success' : 'danger'">
    </section>

    <!-- Contatti Recipient -->
    @if($this->recipient)
        <flux:card>
            <flux:text>{{ $this->recipient->full_name }}</flux:text>
            <!-- telefono, indirizzo, contatti emergenza -->
        </flux:card>
    @else
        <flux:callout variant="warning">Nessun recipient associato</flux:callout>
    @endif

    <!-- Controlli (solo se canControl e isOnline) -->
    @if($this->canControl && $this->isOnline)
        <livewire:dashboard.controls.audio-player :onesiBox="$onesiBox" />
        <livewire:dashboard.controls.video-player :onesiBox="$onesiBox" />
        <livewire:dashboard.controls.zoom-call :onesiBox="$onesiBox" />
    @endif
</div>
```

---

## Component: Dashboard\Controls\AudioPlayer

**Purpose**: Form per avviare riproduzione audio

### Public Properties

| Property | Type | Validation | Description |
|----------|------|------------|-------------|
| `onesiBox` | `OnesiBox` | — | Injected from parent |
| `audioUrl` | `string` | required, url, max:2048 | URL contenuto audio |

### Actions

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `playAudio` | — | `void` | Invia comando audio |

### Authorization

```php
public function playAudio(): void
{
    $this->authorize('control', $this->onesiBox);
    // ...
}
```

### Blade Template

```blade
<flux:card>
    <flux:heading size="sm">Riproduzione Audio</flux:heading>
    <form wire:submit="playAudio" class="space-y-4">
        <flux:input
            wire:model="audioUrl"
            label="URL Audio"
            type="url"
            placeholder="https://..."
        />
        <flux:button type="submit" variant="primary">
            Riproduci
        </flux:button>
    </form>
</flux:card>
```

---

## Component: Dashboard\Controls\VideoPlayer

**Purpose**: Form per avviare riproduzione video

### Public Properties

| Property | Type | Validation | Description |
|----------|------|------------|-------------|
| `onesiBox` | `OnesiBox` | — | Injected from parent |
| `videoUrl` | `string` | required, url, max:2048 | URL contenuto video |

### Actions

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `playVideo` | — | `void` | Invia comando video |

### Blade Template

```blade
<flux:card>
    <flux:heading size="sm">Riproduzione Video</flux:heading>
    <form wire:submit="playVideo" class="space-y-4">
        <flux:input
            wire:model="videoUrl"
            label="URL Video"
            type="url"
        />
        <flux:button type="submit" variant="primary">
            Riproduci
        </flux:button>
    </form>
</flux:card>
```

---

## Component: Dashboard\Controls\ZoomCall

**Purpose**: Form per avviare chiamata Zoom

### Public Properties

| Property | Type | Validation | Description |
|----------|------|------------|-------------|
| `onesiBox` | `OnesiBox` | — | Injected from parent |
| `meetingId` | `string` | required, regex:/^\d{9,11}$/ | ID meeting Zoom |
| `password` | `string` | nullable, max:10 | Password meeting (opzionale) |

### Actions

| Method | Parameters | Returns | Description |
|--------|------------|---------|-------------|
| `startCall` | — | `void` | Invia comando Zoom |
| `endCall` | — | `void` | Termina chiamata in corso |

### Blade Template

```blade
<flux:card>
    <flux:heading size="sm">Chiamata Zoom</flux:heading>
    <form wire:submit="startCall" class="space-y-4">
        <flux:input
            wire:model="meetingId"
            label="Meeting ID"
            placeholder="123456789"
        />
        <flux:input
            wire:model="password"
            label="Password (opzionale)"
            type="password"
        />
        <flux:button type="submit" variant="primary">
            Avvia Chiamata
        </flux:button>
    </form>
</flux:card>
```

---

## Shared Behaviors

### Loading States

Tutti i form usano `wire:loading` per feedback visivo:

```blade
<flux:button type="submit" wire:loading.attr="disabled">
    <span wire:loading.remove>Riproduci</span>
    <span wire:loading>Invio in corso...</span>
</flux:button>
```

### Error Handling

```php
try {
    $this->commandService->sendAudioCommand($this->onesiBox, $this->audioUrl);
    Flux::toast('Comando inviato con successo');
} catch (OnesiBoxOfflineException $e) {
    Flux::toast('OnesiBox non raggiungibile', variant: 'danger');
}
```

### Authorization Pattern

Tutti i componenti di controllo verificano:
1. `authorize('control', $onesiBox)` prima di ogni azione
2. `$onesiBox->isOnline()` per abilitare/disabilitare UI
