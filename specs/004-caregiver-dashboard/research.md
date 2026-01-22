# Research: Caregiver Dashboard

**Feature**: 004-caregiver-dashboard
**Date**: 2026-01-22

## 1. Real-time Updates Strategy

### Decision: Laravel Echo + Livewire Events con fallback Polling

### Rationale
- Laravel Reverb 1.7.0 già configurato nel progetto
- Livewire 4 supporta nativamente `#[On('echo:channel,Event')]` per WebSocket events
- Polling come fallback garantisce funzionamento anche senza WebSocket

### Alternatives Considered
| Alternative | Pro | Contro | Motivo esclusione |
|-------------|-----|--------|-------------------|
| Solo Polling | Semplice, nessuna dipendenza WebSocket | Resource intensive, latenza alta | Non rispetta SC-002 (<3s update) |
| Server-Sent Events | Unidirezionale, leggero | Non supportato nativamente da Livewire | Complessità extra non necessaria |
| Echo + Polling fallback | Best of both worlds | Leggermente più complesso | **SELEZIONATO** |

### Implementation Pattern
```php
// Nel componente Livewire
#[On('echo-private:onesibox.{onesiBox.id},StatusUpdated')]
public function handleStatusUpdate(array $payload): void
{
    $this->onesiBox->refresh();
}
```

```blade
<!-- Fallback polling se Echo non disponibile -->
<div wire:poll.5s.visible>
```

---

## 2. Authorization Strategy

### Decision: Laravel Policy con metodi view/control

### Rationale
- Pattern standard Laravel per autorizzazione resource-based
- Integrazione nativa con `$this->authorize()` nei componenti Livewire
- Permette controllo granulare Full vs ReadOnly

### Implementation Pattern
```php
// OnesiBoxPolicy.php
public function view(User $user, OnesiBox $onesiBox): bool
{
    return $onesiBox->userCanView($user);
}

public function control(User $user, OnesiBox $onesiBox): bool
{
    return $onesiBox->userHasFullPermission($user);
}
```

---

## 3. Command Dispatch Architecture

### Decision: Service Class + Queued Jobs

### Rationale
- Separazione responsabilità (componente UI non gestisce comunicazione appliance)
- Jobs permettono retry automatico in caso di fallimento temporaneo
- Feedback immediato all'utente mentre il comando viene processato

### Alternatives Considered
| Alternative | Pro | Contro | Motivo esclusione |
|-------------|-----|--------|-------------------|
| Direct API call dal componente | Semplice | Blocking, nessun retry | Non rispetta SC-005 (feedback <1s) |
| Event + Listener | Disaccoppiato | Over-engineering per questo caso | YAGNI |
| Service + Job | Testabile, resiliente | Leggermente più codice | **SELEZIONATO** |

### Implementation Pattern
```php
// OnesiBoxCommandService.php
public function sendAudioCommand(OnesiBox $onesiBox, string $audioUrl): void
{
    SendOnesiBoxCommand::dispatch($onesiBox, 'audio', ['url' => $audioUrl]);
}
```

---

## 4. Mobile-First UI Components

### Decision: Flux UI con layout stack verticale

### Rationale
- Flux UI 2.10.2 già nel progetto, componenti responsive out-of-the-box
- Stack verticale per mobile, grid per tablet/desktop
- Touch targets ≥44px per accessibilità mobile

### Component Strategy
| Screen | Layout | Components |
|--------|--------|------------|
| Lista | Stack verticale cards | `<flux:card>` per ogni OnesiBox |
| Dettaglio | Stack con sezioni | Header stato, Card recipient, Controlli |
| Controlli | Form verticale | `<flux:input>`, `<flux:button>` |

### Breakpoint Strategy
```blade
<div class="flex flex-col gap-4 lg:grid lg:grid-cols-2 lg:gap-6">
```

---

## 5. Broadcast Event Structure

### Decision: Private channel per OnesiBox

### Rationale
- Privacy: solo caregiver autorizzati ricevono eventi
- Granularità: un channel per appliance
- Efficienza: no broadcast inutili

### Channel Authorization
```php
// routes/channels.php
Broadcast::channel('onesibox.{id}', function (User $user, int $id) {
    $onesiBox = OnesiBox::find($id);
    return $onesiBox && $onesiBox->userCanView($user);
});
```

### Event Payload
```php
// OnesiBoxStatusUpdated.php
public function broadcastWith(): array
{
    return [
        'status' => $this->onesiBox->status,
        'is_online' => $this->onesiBox->isOnline(),
        'last_seen_at' => $this->onesiBox->last_seen_at?->toISOString(),
    ];
}
```

---

## 6. Form Validation Strategy

### Decision: Inline validation con Livewire rules

### Rationale
- Feedback immediato all'utente
- No Form Request per form semplici (YAGNI)
- Validazione lato server sempre presente

### Validation Rules
| Field | Rules |
|-------|-------|
| audio_url | required, url, max:2048 |
| video_url | required, url, max:2048 |
| zoom_meeting_id | required, string, regex:/^\d{9,11}$/ |
| zoom_password | nullable, string, max:10 |

---

## 7. Error Handling Strategy

### Decision: Toast notifications via Flux + graceful degradation

### Rationale
- Flux UI fornisce sistema di notifiche integrato
- Messaggi user-friendly, no stack trace
- Retry automatico per errori temporanei

### Error Categories
| Categoria | Messaggio | Azione |
|-----------|-----------|--------|
| Offline | "OnesiBox non raggiungibile" | Disabilita controlli |
| Permission | "Non hai i permessi" | Nascondi controllo |
| Validation | Dettaglio campo | Focus sul campo |
| Network | "Errore di connessione" | Retry button |

---

## Research Gaps Closed

Tutti i NEEDS CLARIFICATION dal Technical Context sono stati risolti:

1. **Real-time mechanism**: Echo + Polling fallback ✓
2. **Authorization pattern**: Laravel Policy ✓
3. **Command dispatch**: Service + Queued Jobs ✓
4. **Mobile UI**: Flux UI stack verticale ✓
5. **Broadcast structure**: Private channels ✓
