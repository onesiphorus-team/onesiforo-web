# Workshop - Introduzione al Progetto Onesiforo

**Data:** Sabato 1 Marzo 2026
**Durata:** ~3-4 ore

---

## Agenda

### Parte 1: Visione e Contesto (30 min)

**Obiettivo:** Capire cosa fa il sistema e perché esiste.

- Cos'è Onesiforo e chi sono gli utenti (caregiver, beneficiari, admin)
- Demo live del sistema:
  - Dashboard caregiver con OnesiBox collegata
  - Invio comandi (play video, volume, zoom)
  - Pannello admin Filament
- Architettura ad alto livello (diagramma di contesto)

**Documento di riferimento:** `01-ARCHITETTURA-SISTEMA.md` - Sezioni 1-2

---

### Parte 2: Deep Dive Tecnico - Backend (45 min)

**Obiettivo:** Comprendere l'architettura del backend Laravel.

#### Argomenti

1. **Stack e struttura directory**
   - Laravel 12 + Livewire 4 + Filament 5
   - Struttura `app/` (Actions, Services, Livewire, Filament)

2. **Domain Model**
   - Entità principali: User, OnesiBox, Command, Playlist, PlaybackSession
   - Diagramma ER

3. **Pattern architetturali**
   - Action Pattern (business logic)
   - Service Pattern (OnesiBoxCommandService)
   - Thin controllers, rich actions

4. **Sistema comandi**
   - Lifecycle: Creazione -> Job -> WebSocket -> Polling -> Esecuzione -> ACK
   - Priorità e scadenze
   - Pessimistic locking per ACK

5. **Sistema sessioni playlist**
   - Start -> Advance -> Complete/Expire
   - Protezione avanzamento duplicato

6. **Real-time con Reverb**
   - WebSocket per dashboard (status update)
   - WebSocket per client (push comandi)
   - Polling come fallback

**Documento di riferimento:** `01-ARCHITETTURA-SISTEMA.md` - Sezioni 2-5

---

### Pausa (15 min)

---

### Parte 3: Deep Dive Tecnico - Client OnesiBox (45 min)

**Obiettivo:** Comprendere il funzionamento del client su Raspberry Pi.

#### Argomenti

1. **Architettura client**
   - Node.js + Playwright + Chromium kiosk
   - Componenti: Communication, Commands, Browser, State

2. **Comunicazione ibrida**
   - HTTP polling (fallback) + WebSocket (primary)
   - Adaptive polling: 5s senza WS, 30s con WS

3. **Pipeline comandi**
   - Validazione -> Prioritizzazione -> Esecuzione -> ACK
   - URL whitelist per sicurezza
   - Handler per tipo di comando

4. **Gestione media**
   - Player JW.org (proxy CDN + player.html custom)
   - Video-ended detection (polling 2s + flag window)
   - Zoom via Playwright separato

5. **Resilienza**
   - Crash recovery del browser
   - Error recovery automatico (10s)
   - Watchdog systemd
   - Auto-update via git pull + cron

6. **Deploy su Raspberry Pi**
   - install.sh interattivo
   - Wayland/labwc per kiosk
   - Systemd service + watchdog

**Documento di riferimento:** `01-ARCHITETTURA-SISTEMA.md` - Sezione 3

---

### Parte 4: Contratto API (30 min)

**Obiettivo:** Capire come comunicano backend e client.

#### Argomenti

1. **4 endpoint REST**
   - Heartbeat, Commands poll, Command ACK, Playback events
2. **Formato richieste/risposte** con esempi
3. **Rate limiting**
4. **WebSocket events** (StatusUpdated, NewCommandAvailable)
5. **Codici errore**

**Documento di riferimento:** `03-API-REFERENCE.md`

---

### Pausa (15 min)

---

### Parte 5: Qualità del Codice e Refactoring (30 min)

**Obiettivo:** Identificare le aree di miglioramento e pianificare il lavoro futuro.

#### Argomenti

1. **Problemi critici da risolvere subito**
   - Gate API docs aperto
   - XSS in player.html
   - ACK retry mancante

2. **Debito tecnico**
   - 7 comandi non implementati nel client
   - Dead code (AutoUpdater)
   - Logica duplicata

3. **Gap nei test**
   - Componenti Livewire non testati
   - Handler client critici non testati

4. **Ottimizzazioni proposte**
   - Pulizia PlaybackEvent schedulata
   - Expire sessions via SQL
   - Riduzione activity log da heartbeat

**Documento di riferimento:** `04-ANALISI-E-REFACTORING.md`

---

### Parte 6: Setup e Hands-on (30 min)

**Obiettivo:** Ogni sviluppatore ha l'ambiente funzionante.

1. Clone dei due repository
2. Setup backend con Herd
3. Setup client locale
4. Esecuzione test suite
5. Esplorazione della codebase

**Documento di riferimento:** `02-GUIDA-SVILUPPO.md`

---

## Documenti del Workshop

| # | Documento | Contenuto |
|---|----------|----------|
| 1 | `01-ARCHITETTURA-SISTEMA.md` | Architettura completa con diagrammi Mermaid |
| 2 | `02-GUIDA-SVILUPPO.md` | Setup, convenzioni, workflow, testing |
| 3 | `03-API-REFERENCE.md` | Documentazione API completa |
| 4 | `04-ANALISI-E-REFACTORING.md` | Problemi, fix proposti, gap test |
| 5 | `05-WORKSHOP-AGENDA.md` | Questo documento - agenda e scaletta |

---

## Preparazione Partecipanti

Prima del workshop, assicurarsi di avere:

- [ ] PHP 8.4+ installato
- [ ] Composer 2.x installato
- [ ] Node.js 20+ installato
- [ ] Laravel Herd installato e configurato
- [ ] Git configurato con accesso ai repository
- [ ] Editor/IDE pronto (VS Code, PHPStorm, etc.)
- [ ] SQLite disponibile (di solito già presente)

---

## Risorse Aggiuntive

- **Documentazione esistente:** `docs/` in entrambi i repository
- **API Docs auto-generata:** `/docs/api` (Scramble) sul server
- **Laravel Docs:** https://laravel.com/docs/12.x
- **Livewire Docs:** https://livewire.laravel.com
- **Filament Docs:** https://filamentphp.com/docs
- **Pest Docs:** https://pestphp.com/docs
