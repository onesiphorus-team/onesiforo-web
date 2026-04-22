# Dashboard Detail — Mobile Redesign

- **Stato:** design approvato, pronto per planning
- **Data:** 2026-04-22
- **Branch:** `feat/dashboard-detail-mobile-redesign`
- **Route interessata:** `GET /dashboard/{onesiBox}` (`dashboard.show`)
- **Componente root:** `App\Livewire\Dashboard\OnesiBoxDetail`
- **Vincolo di utenza:** 80% degli utilizzi avviene da smartphone; il caregiver è in modalità *controllo attivo* (lancia audio/video/stream/Zoom dal telefono, non solo monitora).

## Obiettivo

Ristrutturare la pagina di dettaglio OnesiBox in ottica **mobile-first** per:

1. Ridurre drasticamente lo scroll verticale (oggi ~14 sezioni al primo livello).
2. Rendere le azioni più frequenti raggiungibili dal pollice senza tornare in cima.
3. Stabilire una gerarchia visiva chiara: *stato corrente* > *azione rapida* > *contesto* > *amministrazione*.
4. Mantenere intatti permessi, polling, eventi Livewire e contratti API esistenti.

Non cambiamo:

- Contratti API (`/api/v1/appliances/*`).
- Logica di dispatch comandi (`OnesiBoxCommandService`).
- Permessi caregiver/admin/super-admin.
- Schema DB.

## Architettura della pagina (mobile 390×844)

```
┌─────────────────────────────────┐
│  ← Dev Test  ● v1.2.3       [⋯] │  header sticky ~56px
├─────────────────────────────────┤
│                                 │
│   ╔═════════════════════════╗   │
│   ║   HERO CARD             ║   │  stato corrente, 4 varianti
│   ║   (idle / media /       ║   │  ~180px
│   ║    call / offline)      ║   │
│   ╚═════════════════════════╝   │
│                                 │
│   ▸ Sessione in corso      (●)  │  accordion (flux:accordion)
│   ▸ Comandi in coda        (3)  │  con badge condizionali
│   ▸ Prossimo meeting        ⏱  │
│   ▸ Playlist & programmazione   │
│   ▸ Contatti destinatario       │
│   ▸ Meeting programmati         │
│                                 │
│   ── Amministrazione ──         │  solo admin / super-admin
│   ▸ Sistema                     │
│   ▸ Rete                        │
│   ▸ Controlli sistema           │
│   ▸ Log                         │
│                                 │
│   [ padding ~88px ]             │
├─────────────────────────────────┤
│  ⏹      🔊      ＋ Nuovo     📞 │  bottom bar sticky ~72px
└─────────────────────────────────┘
```

### Zone

1. **Header sticky (~56px).** Back, nome OnesiBox, indicatore online (pallino con ping), versione, menu `⋯` per azioni rare.
2. **Hero card.** Stato corrente (vedi sotto: 4 varianti).
3. **Accordion corpo.** `flux:accordion` multi-selezione; chiusi di default salvo condizioni che li marcano "attivi".
4. **Sezione Amministrazione.** Stesso pattern accordion, gated da `$this->isAdmin`, introdotta da divisor con label.
5. **Bottom bar sticky (~72px + `env(safe-area-inset-bottom)`).** 4 slot: Stop / Volume / Nuovo / Chiama.

## Hero card — 4 varianti

Stato derivato da una computed property `heroState: 'media' | 'call' | 'idle' | 'offline'` su `OnesiBoxDetail`.

### `media` — audio / video / stream in riproduzione

- Tag colorato piccolo con il tipo (`AUDIO` / `VIDEO` / `STREAM`).
- Titolo in `text-lg font-semibold` con `line-clamp-2`.
- Sorgente (`host` dell'URL) in `text-xs muted`.
- **Progress bar** basata sull'ultimo `PlaybackEvent.position` / `duration` per la media corrente. Nasconde la barra se entrambi nulli.
- Due pulsanti inline:
  - `⏸ Pausa` → dispatch `CommandType::PauseMedia` (o `ResumeMedia` se già in pausa — stato dedotto dall'ultimo `PlaybackEventType`).
  - `⏹ Stop` → dispatch `CommandType::StopMedia` sul media attivo (distinto dallo "Stop tutto" della bottom bar che continua a usare `StopAllPlayback`).

### `call` — Zoom meeting in corso

- Titolo "Chiamata in corso".
- Meeting ID e durata (differenza fra `playback_events.created_at` dell'ultimo `call_started` e ora).
- Pulsante danger full-width `Termina chiamata` → dispatch `CommandType::LeaveZoom`.

### `idle` — online, niente attivo

- Riga di stato: `● Online · in attesa`.
- `Ultimo contatto: …`.
- `Prossimo meeting: …` (solo se entro 24h, derivato dalla stessa query del prossimo accordion).
- 2 scorciatoie: `Avvia sessione` (apre il bottom sheet con "Dalle playlist salvate") e `＋ Nuovo` (stessa bottom sheet).

### `offline`

- Sfondo `amber-50 / amber-900/20`, icona warning.
- Ultimo contatto + versione in grigio.
- Tutti i pulsanti interni disabilitati.

## Bottom bar

Renderizzata solo se `$this->canControl === true`. Fissata in basso con `position: sticky; bottom: 0` su un contenitore flex esterno che consente scroll del body.

| Slot | Simbolo | Azione tap | Stato disabilitato |
|------|---------|-----------|--------------------|
| 1 | `⏹` (rosso se attivo) | Dispatch `StopAllPlayback` | hero state ≠ `media` AND ≠ `call` |
| 2 | `🔊` | Apre `flux:popover` con slider volume 0–100 step 5 | `!isOnline` |
| 3 | `＋ Nuovo` (primario, span 2) | Apre bottom sheet "Riproduci…" | `!isOnline` |
| 4 | `📞` diventa `📵` rosso se in call | Avvia Zoom call quick → destinatario; se in call, termina | `!isOnline` |

- Tap target ≥ 56×56px.
- `aria-label` esplicito su ogni pulsante.
- Se `!isOnline`: bar visibile ma con `opacity-40 pointer-events-none` (feedback visivo che i controlli tornano al ripristino online).

## Bottom sheet "Riproduci…"

Componente Livewire dedicato: `App\Livewire\Dashboard\Controls\QuickPlaySheet`.

Stati interni:

1. **Menu iniziale** — lista: Audio da URL / Video da URL / Stream YouTube / Dalle playlist salvate.
2. **Sotto-form** — una volta scelto, il sheet si espande mostrando il form del player corrispondente. I form riusano la logica dei componenti esistenti (`AudioPlayer`, `VideoPlayer`, `StreamPlayer`) o ne estraggono una versione "sheet-mode" se necessario per il binding.
3. **Playlist salvate** — lista derivata da `Playlist::query()->where('user_id', …)`; tap avvia la sessione (riusa `StartPlaybackSessionAction`).

UI:
- Si apre con `flux:modal` variant `flyout` dal basso, drag handle in alto, swipe-down chiude.
- Chiusura automatica dopo submit riuscito, con conferma toast Flux.

Out of scope del sheet:
- **Meeting programmati** (pianificazione futura) → restano nell'accordion "Meeting programmati".
- **Zoom quick call** → pulsante `📞` dedicato nella bottom bar.

## Accordion — ordine e comportamento

Tutti `flux:accordion` con `exclusive=false`. Stato default "aperto" calcolato lato server in base ai badge.

| # | Accordion | Aperto di default se… | Badge | Contenuto |
|---|-----------|----------------------|-------|-----------|
| 1 | Sessione in corso | `PlaybackSession::active` esiste | pallino verde pulsante | `<livewire:dashboard.controls.session-status>` esistente |
| 2 | Comandi in coda | `Command` `pending` o `sent` | numero | `<livewire:dashboard.controls.command-queue>` |
| 3 | Prossimo meeting | prossimo `MeetingInstance` entro 24h | ora/data | info meeting + pulsante unisci |
| 4 | Playlist & programmazione | mai | — | wrapper con `SessionManager` + `SavedPlaylists` + `PlaylistBuilder` |
| 5 | Contatti destinatario | mai | — | info attuali (nome, telefono, indirizzo, emergenza) |
| 6 | Meeting programmati | mai | — | `<livewire:dashboard.controls.meeting-schedule>` |
| — | **Amministrazione** (gated `isAdmin`) | — | — | divisor + 4 accordion sotto |
| 7 | Sistema | mai | warning icon se CPU/temp oltre soglia | `<livewire:dashboard.controls.system-info>` |
| 8 | Rete | mai | — | `<livewire:dashboard.controls.network-info>` |
| 9 | Controlli sistema | mai | — | `<livewire:dashboard.controls.system-controls>` |
| 10 | Log | mai | numero errori 24h | `<livewire:dashboard.controls.log-viewer>` |

Motivazione dell'ordine: la sessione/coda è "cosa sta succedendo adesso", il meeting imminente è un'informazione sensibile al tempo, playlist/contatti/meeting programmati sono contesto, admin è manutenzione.

## Stati edge

| Scenario | Header | Hero | Accordion | Bottom bar |
|----------|--------|------|-----------|------------|
| `!$recipient` | visibile | nascosta | callout warning dedicato in cima | nascosta |
| `!$this->canControl` (read-only) | visibile | visibile (solo monitoraggio, no pulsanti interni) | visibili tutti quelli non admin | nascosta |
| `!$this->isOnline` | pallino grigio | variante offline | "Sessione in corso" e "Comandi in coda" ancora accessibili per storico | ghost (visibile disabilitata) |
| `status === Error` | badge rosso | banner errore sopra hero | normale | normale |

## Tipografia, palette, tap targets

- **Header name:** `text-base font-semibold` (ridotto da `text-xl`) per risparmiare spazio sticky.
- **Hero title:** `text-lg font-semibold`, `line-clamp-2`.
- **Accordion label:** `text-sm font-medium`.
- **Body accordion:** scale Flux default.
- **Palette (invariate, già nel codice):**
  - Online `green-500/400` — Offline `zinc-400`
  - Media `green-50/900 bg`, `green-700/300 text`
  - Call `blue-50/900 bg`, `blue-700/300 text`
  - Warning `amber-500` — Stop `red-500`
- **Tap targets:** ≥44×44px (WCAG 2.1 AA), bottom bar 56×56.
- **Safe-area iOS:** `pb-[env(safe-area-inset-bottom)]` sulla bottom bar.
- **Scroll offset:** `scroll-mt-14` sulle sezioni per ancora-jump sotto l'header sticky.

## Accessibilità

- `aria-label` esplicito su tutti i pulsanti icon-only (bottom bar, header menu, hero controls).
- Hero con `aria-live="polite"` per annunciare cambi di stato (da `idle` a `media` ecc.).
- Pallino online con `role="status"` e testo nascosto "Online"/"Offline".
- Ordine DOM logico: header → hero → accordion → bottom bar (per keyboard nav).
- Focus ring Flux default mantenuto, non sovrascritto.
- Contrasti testo verificati: green-700 su green-50 e blue-700 su blue-50 soddisfano WCAG AA per `text-sm`+.

## Dark mode

Tutte le card e la bottom bar hanno varianti `dark:` (pattern già presente nei componenti esistenti; va replicato su `HeroCard`, `BottomBar`, `QuickPlaySheet`).

## Wiring Livewire

- `wire:poll.15s="refreshFromDatabase"` resta sul container principale.
- La hero e la bottom bar **non** introducono nuovi `wire:poll`: leggono da computed properties del parent.
- Volume popover: riusa `VolumeControl` esistente dentro `flux:popover` — nessuna logica nuova.
- Bottom sheet: singolo componente `QuickPlaySheet`, che riusa le action / factory dei player esistenti. Niente richieste duplicate al backend.

## Architettura componenti

### Nuovi componenti Livewire

| Classe | View | Responsabilità |
|--------|------|----------------|
| `App\Livewire\Dashboard\Controls\HeroCard` | `livewire/dashboard/controls/hero-card.blade.php` | Rendering delle 4 varianti stato corrente |
| `App\Livewire\Dashboard\Controls\BottomBar` | `livewire/dashboard/controls/bottom-bar.blade.php` | Slot sticky Stop/Volume/Nuovo/Chiama |
| `App\Livewire\Dashboard\Controls\QuickPlaySheet` | `livewire/dashboard/controls/quick-play-sheet.blade.php` | Bottom sheet riproduzione rapida |

Ognuno ha la sua boundary netta:
- `HeroCard` riceve `$onesiBox` e derivati, non dispatch-a comandi direttamente (usa eventi Livewire verso il parent o action shared).
- `BottomBar` dispatcha comandi via `OnesiBoxCommandService`; emette eventi per aprire il `QuickPlaySheet`.
- `QuickPlaySheet` è completamente autosufficiente: riceve `$onesiBox`, gestisce il form, chiude via `$dispatch('quick-play-closed')`.

### Modifiche a `OnesiBoxDetail`

- Nuove computed:
  - `heroState(): 'idle'|'media'|'call'|'offline'`
  - `isInCall(): bool`
  - `accordionDefaults(): array<string,bool>` (per capire quali aprire)
- Nessun metodo rimosso: le action esistenti restano.

### Componenti riusati inalterati

`SessionStatus`, `SessionManager`, `SavedPlaylists`, `PlaylistBuilder`, `CommandQueue`, `AudioPlayer`, `VideoPlayer`, `StreamPlayer`, `ZoomCall`, `MeetingSchedule`, `VolumeControl`, `StopAllPlayback`, `SystemInfo`, `NetworkInfo`, `SystemControls`, `LogViewer`.

## Testing

Tutti i test nuovi in Pest 4; browser smoke test con viewport mobile.

### Nuovi test

- `tests/Feature/Livewire/Dashboard/Controls/HeroCardTest.php` — rendering dei 4 stati (idle / media / call / offline), progress bar mostrata/nascosta, pulsanti pausa/stop disabilitati se offline.
- `tests/Feature/Livewire/Dashboard/Controls/BottomBarTest.php` — slot disabilitati correttamente (offline, no media, read-only), dispatch corretto dei comandi.
- `tests/Feature/Livewire/Dashboard/Controls/QuickPlaySheetTest.php` — apertura e switch fra i sotto-form, submit per ogni tipo, chiusura su successo.
- `tests/Browser/DashboardDetailMobileTest.php` — smoke test viewport 390×844 su stato idle e media, verifica assenza errori console JS.

### Test da aggiornare

- `tests/Feature/Livewire/Dashboard/OnesiBoxDetailTest.php` — adeguare le assertion DOM alla nuova struttura (selector per hero, accordion, bottom bar).

### Esecuzione

```
php artisan test --compact --filter=HeroCard
php artisan test --compact --filter=BottomBar
php artisan test --compact --filter=QuickPlay
php artisan test --compact --filter=OnesiBoxDetail
php artisan test --compact --filter=DashboardDetailMobile
```

## Non-obiettivi (YAGNI)

- **Desktop breakpoint dedicato.** La pagina resta single-column fino a `md`; il redesign è mobile-first. Il desktop riceve lo stesso layout mobile entro `max-w-4xl` (come ora). Nessuna versione "tablet/desktop" dedicata in questa iterazione.
- **Gestione push notifications** per cambi di stato — fuori scope.
- **Nuovi comandi OnesiBox** — tutti esistenti (`PauseMedia`, `ResumeMedia`, `StopMedia`, `JoinZoom`, `LeaveZoom`, `SetVolume` ecc.).
- **Riordinamento accordion drag&drop** — non richiesto.
- **Pausa/progress per stream YouTube** — dipende dagli eventi riportati dal client; se lo stream non manda `position`, la progress non viene mostrata (già previsto come fallback).

## Rischi aperti

1. **Dove mettere lo scroll principale.** `flux:accordion` dentro un container con bottom bar sticky può dare attriti se gli accordion stessi hanno overflow interno. Pattern scelto: **body scroll** unico sull'intera pagina con header e bottom bar `sticky`. Da verificare in implementazione con viewport iOS Safari (dove `100vh` è problematico).
2. **Bottom sheet su iOS Safari.** `flux:modal` flyout va testato su iOS per scroll-lock corretto e safe-area. Fallback: se ci sono problemi, degradare a modal a schermo intero mobile.
3. **Stream sorgente "title"**. Oggi la hero MEDIA espone un "titolo": per stream/URL arbitrari non sempre c'è un titolo umano; fallback: hostname dell'URL.

## Definition of Done

- [ ] Branch `feat/dashboard-detail-mobile-redesign` con tutti i commit.
- [ ] Tutti i test esistenti passano (`php artisan test --compact`).
- [ ] Nuovi test (Hero/BottomBar/QuickPlay/Browser) verdi.
- [ ] `vendor/bin/pint --dirty --format agent` eseguito.
- [ ] PHPStan (`vendor/bin/phpstan analyse`) senza nuovi errori.
- [ ] Verifica manuale (viewport iPhone 12/13 Safari, Android Chrome) dei 4 stati hero e della bottom bar.
- [ ] PR aperta contro `main` con screenshot before/after mobile.
