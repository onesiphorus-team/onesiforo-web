# Feature Specification: OnesiBox API Webservices

**Feature Branch**: `003-onesibox-api-ws`
**Created**: 2026-01-22
**Status**: Draft
**Input**: User description: "Implementare le webservice che consentono a OnesiBox di contattare Onesiforo. API con ApiResource e FormRequest, autenticazione tramite token, documentazione con dedoc/scramble."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - OnesiBox Retrieves Pending Commands (Priority: P1)

L'appliance OnesiBox, dopo aver stabilito la connessione con Onesiforo, deve poter recuperare i comandi pendenti da eseguire. Questo permette al sistema di funzionare in modalita polling (Fase 1) dove l'appliance interroga periodicamente il server per ottenere nuove istruzioni.

**Why this priority**: Questa e la funzionalita core che abilita la comunicazione bidirezionale tra caregiver e appliance. Senza di essa, l'appliance non puo ricevere istruzioni e il sistema non puo funzionare.

**Independent Test**: Puo essere testato creando comandi nel database e verificando che l'appliance li riceva correttamente tramite l'endpoint.

**Acceptance Scenarios**:

1. **Given** un'appliance OnesiBox autenticata con token valido e comandi pendenti in coda, **When** l'appliance richiede i comandi, **Then** riceve la lista dei comandi pendenti ordinati per priorita e data di creazione.
2. **Given** un'appliance OnesiBox autenticata senza comandi pendenti, **When** l'appliance richiede i comandi, **Then** riceve una lista vuota.
3. **Given** un'appliance OnesiBox con token non valido, **When** tenta di recuperare i comandi, **Then** riceve errore 401 Unauthorized.
4. **Given** un'appliance OnesiBox disabilitata, **When** tenta di recuperare i comandi, **Then** riceve errore 403 Forbidden.

---

### User Story 2 - OnesiBox Conferma Esecuzione Comando (Priority: P1)

Dopo aver eseguito un comando, l'appliance OnesiBox deve confermare al server l'esito dell'esecuzione. Questo permette al sistema di tracciare lo stato dei comandi e al caregiver di vedere se le sue azioni sono state eseguite correttamente.

**Why this priority**: Il feedback sull'esecuzione dei comandi e essenziale per l'affidabilita del sistema e per dare visibilita al caregiver sullo stato delle operazioni.

**Independent Test**: Puo essere testato inviando un acknowledgment per un comando esistente e verificando che lo stato venga aggiornato nel database.

**Acceptance Scenarios**:

1. **Given** un comando esistente in stato pending e un'appliance autenticata, **When** l'appliance conferma l'esecuzione con esito positivo, **Then** lo stato del comando viene aggiornato a "completed" con timestamp di esecuzione.
2. **Given** un comando esistente e un'appliance autenticata, **When** l'appliance segnala un errore nell'esecuzione, **Then** lo stato del comando viene aggiornato a "failed" con codice e messaggio di errore.
3. **Given** un comando inesistente, **When** l'appliance tenta di confermare, **Then** riceve errore 404 Not Found.
4. **Given** un comando che appartiene ad un'altra appliance, **When** l'appliance tenta di confermare, **Then** riceve errore 403 Forbidden.

---

### User Story 3 - OnesiBox Aggiorna Stato Riproduzione (Priority: P2)

Durante la riproduzione di contenuti multimediali, l'appliance OnesiBox deve poter notificare al server i cambiamenti di stato (avvio, pausa, stop, completamento, errore). Questo permette al caregiver di monitorare in tempo reale cosa sta accadendo sull'appliance.

**Why this priority**: Il monitoraggio dello stato di riproduzione e importante per l'esperienza utente del caregiver, ma il sistema puo funzionare anche senza questa visibilita in tempo reale.

**Independent Test**: Puo essere testato inviando eventi di playback e verificando che vengano registrati correttamente nel sistema.

**Acceptance Scenarios**:

1. **Given** un'appliance autenticata che avvia una riproduzione video, **When** invia l'evento "started" con URL e tipo media, **Then** lo stato viene registrato e il caregiver puo vederlo.
2. **Given** un'appliance in riproduzione, **When** invia l'evento "paused" con posizione corrente, **Then** lo stato viene aggiornato con la posizione di pausa.
3. **Given** un'appliance in riproduzione, **When** la riproduzione termina naturalmente, **Then** l'appliance invia l'evento "completed" e lo stato viene aggiornato.
4. **Given** un'appliance con errore di riproduzione, **When** invia l'evento "error" con messaggio, **Then** l'errore viene registrato per diagnostica.

---

### User Story 4 - Documentazione API Automatica (Priority: P2)

Le API devono essere automaticamente documentate e accessibili tramite l'interfaccia Scramble, permettendo agli sviluppatori di comprendere come integrare l'appliance OnesiBox con il sistema Onesiforo.

**Why this priority**: La documentazione e fondamentale per lo sviluppo e la manutenzione del sistema, ma non blocca il funzionamento operativo.

**Independent Test**: Puo essere verificato accedendo a `/docs/api` e controllando che tutti gli endpoint siano documentati correttamente.

**Acceptance Scenarios**:

1. **Given** le API implementate, **When** uno sviluppatore accede alla documentazione, **Then** vede tutti gli endpoint con descrizioni, parametri, e esempi di response.
2. **Given** un endpoint con validazione complessa, **When** viene visualizzato nella documentazione, **Then** tutte le regole di validazione sono documentate.
3. **Given** un endpoint che ritorna errori, **When** viene visualizzato nella documentazione, **Then** tutti i possibili codici di errore sono elencati.

---

### Edge Cases

- Cosa succede quando un comando scade prima di essere recuperato dall'appliance? Il sistema marca automaticamente il comando come "expired" durante la query GET e lo esclude dalla risposta.
- Come gestire comandi duplicati se l'appliance fa retry? Il sistema risponde con 200 OK senza modificare lo stato (comportamento idempotente).
- Cosa succede se l'appliance invia un heartbeat e poi immediatamente un comando ack? Entrambe le richieste devono essere gestite indipendentemente senza conflitti.
- Cosa succede se l'appliance perde connessione durante l'esecuzione di un comando? Il comando rimane in stato "pending" fino a timeout.
- Come gestire richieste malformate o con payload troppo grande? Rate limiting e validazione rigorosa dei payload.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Il sistema DEVE esporre un endpoint GET per recuperare i comandi pendenti per un'appliance autenticata.
- **FR-002**: Il sistema DEVE esporre un endpoint POST per confermare l'esecuzione di un comando (acknowledgment).
- **FR-003**: Il sistema DEVE esporre un endpoint POST per aggiornare lo stato di riproduzione multimediale.
- **FR-004**: Tutte le API DEVONO autenticare le richieste tramite token Sanctum associato all'appliance.
- **FR-005**: L'autenticazione DEVE verificare che il token appartenga effettivamente all'appliance che effettua la richiesta.
- **FR-006**: I comandi recuperati DEVONO essere ordinati per priorita (alta prima) e poi per data di creazione (piu vecchi prima).
- **FR-007**: Il sistema DEVE supportare il filtraggio dei comandi per stato (pending, all).
- **FR-008**: Il sistema DEVE limitare il numero di comandi restituiti per singola richiesta (default 10, max 50).
- **FR-009**: L'acknowledgment di un comando DEVE registrare timestamp, stato esito, e eventuale messaggio di errore.
- **FR-010**: Gli eventi di playback DEVONO includere tipo evento, URL media, tipo media, posizione e durata.
- **FR-011**: Tutte le risposte API DEVONO utilizzare ApiResource per una struttura consistente.
- **FR-012**: Tutti gli input DEVONO essere validati tramite FormRequest con messaggi di errore in italiano.
- **FR-013**: Le API DEVONO essere documentate automaticamente tramite annotazioni Scramble/OpenAPI.
- **FR-014**: Il sistema DEVE restituire codici di errore specifici per ogni tipo di fallimento (E001-E010 come da architettura).
- **FR-015**: Il sistema DEVE loggare tutte le richieste API per audit trail.
- **FR-016**: Le appliance disabilitate (is_active=false) DEVONO ricevere errore 403 su tutte le richieste.

### Key Entities

- **Command**: Rappresenta un'istruzione da eseguire sull'appliance. Attributi chiave: identificativo univoco, tipo comando, dati specifici del comando, priorita, stato (pending/completed/failed/expired), timestamp creazione, timestamp scadenza (variabile per tipo: 5 min per comandi urgenti come reboot/shutdown, 1 ora per comandi media), timestamp esecuzione, codice errore, messaggio errore.
- **PlaybackEvent**: Rappresenta un evento di riproduzione multimediale. Attributi chiave: tipo evento (started/paused/resumed/stopped/completed/error), URL media, tipo media (audio/video), posizione corrente, durata totale, timestamp. Retention: storico completo persistito per 30 giorni.
- **OnesiBox**: Appliance esistente, esteso con relazione ai comandi. Ogni comando appartiene a una singola appliance.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: L'appliance riceve i comandi pendenti entro 1 secondo dalla richiesta in condizioni normali di carico.
- **SC-002**: Il 99.9% delle richieste di acknowledgment viene processato correttamente senza perdita di dati.
- **SC-003**: La documentazione API copre il 100% degli endpoint implementati con descrizioni complete di parametri, response e errori.
- **SC-004**: Tutte le richieste senza token valido vengono respinte con codice di errore appropriato.
- **SC-005**: Il sistema gestisce almeno 1000 richieste al minuto per singola appliance senza degradazione delle prestazioni.
- **SC-006**: I test automatizzati coprono almeno l'80% del codice delle API con scenari di successo, validazione e errori.
- **SC-007**: I messaggi di errore di validazione sono in italiano e comprensibili per gli sviluppatori dell'appliance.

## Clarifications

### Session 2026-01-22

- Q: Qual e il comportamento di default per la scadenza dei comandi? → A: Scadenza variabile per tipo comando (es. 5 min per comandi urgenti come reboot/shutdown, 1 ora per comandi media)
- Q: Comportamento acknowledgment per comandi gia processati (completed/failed/expired)? → A: Risposta idempotente 200 OK senza modificare lo stato
- Q: Retention degli eventi di playback? → A: Storico completo persistito per 30 giorni
- Q: Gestione comandi scaduti nell'endpoint GET? → A: Filtrare automaticamente e marcare come "expired" durante la query

## Assumptions

- L'autenticazione tramite token Sanctum e gia implementata nel sistema (vedi feature 002-onesibox-management).
- Il pattern esistente di HeartbeatController/HeartbeatRequest/HeartbeatResource verra replicato per i nuovi endpoint.
- I tipi di comando supportati sono quelli definiti nel documento di architettura (play_media, stop_media, pause_media, resume_media, set_volume, join_zoom, leave_zoom, start_jitsi, stop_jitsi, speak_text, show_message, reboot, shutdown, start_vnc, stop_vnc, update_config).
- La priorita dei comandi segue la convenzione 1=alta, 5=bassa.
- Il rate limiting esistente di Laravel (60 req/min per IP, 120 req/min per utente autenticato) e sufficiente.
- La documentazione Scramble e gia configurata e funzionante nel progetto.
