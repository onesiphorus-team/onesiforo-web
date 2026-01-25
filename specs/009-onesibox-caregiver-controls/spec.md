# Feature Specification: OnesiBox Caregiver Controls

**Feature Branch**: `009-onesibox-caregiver-controls`
**Created**: 2026-01-25
**Status**: Draft
**Input**: User description: "Funzionalità caregiver per monitoraggio e controllo Onesibox: stato dispositivo (idle, playing video/audio, Zoom), regolazione volume a 5 livelli (20-40-60-80-100%), gestione coda comandi (visualizza, elimina singolo, elimina tutti), informazioni di sistema (uptime, memoria, CPU, disco, rete WiFi), e log dettagliati con possibilità di richiedere le ultime N righe."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Visualizzazione Stato Attuale OnesiBox (Priority: P1)

Il caregiver visualizza in tempo reale lo stato operativo dell'OnesiBox selezionata. Il sistema mostra chiaramente se l'appliance è in stato idle, sta riproducendo un video (con titolo/URL), sta riproducendo audio (con titolo/URL), o è in una chiamata Zoom. Questa informazione è essenziale per capire cosa sta facendo l'anziano in ogni momento.

**Why this priority**: È la funzionalità fondamentale per il monitoraggio. Senza sapere cosa sta facendo l'appliance, il caregiver non può prendere decisioni informate sui comandi da inviare.

**Independent Test**: Può essere testato simulando diversi stati dell'appliance tramite heartbeat e verificando che l'interfaccia mostri correttamente lo stato con le relative informazioni contestuali.

**Acceptance Scenarios**:

1. **Given** una OnesiBox in stato idle, **When** il caregiver visualizza il dettaglio, **Then** vede lo stato "In attesa" con un'icona appropriata
2. **Given** una OnesiBox che sta riproducendo un video, **When** il caregiver visualizza il dettaglio, **Then** vede lo stato "Riproduzione video" con il titolo o URL del contenuto
3. **Given** una OnesiBox che sta riproducendo audio, **When** il caregiver visualizza il dettaglio, **Then** vede lo stato "Riproduzione audio" con il titolo o URL del contenuto
4. **Given** una OnesiBox in chiamata Zoom, **When** il caregiver visualizza il dettaglio, **Then** vede lo stato "Chiamata Zoom in corso" con l'indicazione del meeting ID
5. **Given** un cambio di stato sull'appliance, **When** il caregiver ha la pagina aperta, **Then** lo stato si aggiorna automaticamente entro 5 secondi senza refresh manuale

---

### User Story 2 - Regolazione Volume (Priority: P1)

Il caregiver può regolare il volume dell'OnesiBox scegliendo tra 5 livelli predefiniti: 20%, 40%, 60%, 80%, 100%. Questa funzionalità è critica perché molti anziani hanno problemi uditivi e il volume deve essere facilmente regolabile da remoto.

**Why this priority**: Il controllo del volume è una delle operazioni più frequenti per garantire che l'anziano possa sentire correttamente i contenuti multimediali o le chiamate.

**Independent Test**: Può essere testato selezionando ogni livello di volume e verificando che il comando venga inviato correttamente all'appliance.

**Acceptance Scenarios**:

1. **Given** un caregiver con permesso "Full" sul dettaglio OnesiBox, **When** visualizza i controlli volume, **Then** vede 5 bottoni/opzioni per i livelli 20%, 40%, 60%, 80%, 100%
2. **Given** un caregiver che seleziona un livello volume, **When** clicca sul livello desiderato, **Then** il comando viene inviato e riceve feedback visivo di conferma
3. **Given** il volume attuale dell'appliance, **When** il caregiver visualizza i controlli, **Then** il livello corrente è evidenziato visivamente
4. **Given** un caregiver con permesso "ReadOnly", **When** visualizza il dettaglio OnesiBox, **Then** i controlli volume non sono disponibili o sono disabilitati
5. **Given** una OnesiBox offline, **When** il caregiver tenta di cambiare volume, **Then** riceve un messaggio che indica l'impossibilità di comunicare con l'appliance

---

### User Story 3 - Visualizzazione e Gestione Coda Comandi (Priority: P2)

Il caregiver può visualizzare i comandi pendenti in coda per l'OnesiBox e può eliminarli singolarmente o tutti insieme. Questo permette di annullare comandi inviati per errore o diventati obsoleti prima che vengano eseguiti.

**Why this priority**: La gestione della coda comandi è importante per evitare che comandi errati o non più desiderati vengano eseguiti, ma il sistema funziona anche senza questa visibilità.

**Independent Test**: Può essere testato creando comandi pendenti nel database, visualizzandoli nell'interfaccia, e verificando che l'eliminazione funzioni correttamente.

**Acceptance Scenarios**:

1. **Given** una OnesiBox con 3 comandi pendenti, **When** il caregiver accede alla sezione coda comandi, **Then** vede la lista dei comandi con tipo, data creazione e priorità
2. **Given** un caregiver che visualizza la coda comandi, **When** clicca per eliminare un singolo comando, **Then** il comando viene rimosso dalla coda dopo conferma
3. **Given** un caregiver che visualizza la coda comandi con più comandi, **When** clicca "Elimina tutti", **Then** tutti i comandi pendenti vengono rimossi dopo conferma
4. **Given** una coda comandi vuota, **When** il caregiver accede alla sezione, **Then** vede un messaggio "Nessun comando in coda"
5. **Given** un caregiver con permesso "ReadOnly", **When** visualizza la coda comandi, **Then** può vedere i comandi ma non può eliminarli
6. **Given** un comando in stato di esecuzione (già prelevato dall'appliance), **When** il caregiver tenta di eliminarlo, **Then** il sistema non permette l'eliminazione

---

### User Story 4 - Informazioni di Sistema (Priority: P2)

Il caregiver può richiedere e visualizzare le informazioni di sistema dell'OnesiBox: uptime con load average, memoria occupata e libera, utilizzo processore, utilizzo disco, indirizzo IP e nome della rete WiFi. Queste informazioni sono utili per diagnosticare problemi di performance o connettività.

**Why this priority**: Le informazioni di sistema sono utili per la diagnostica ma non sono essenziali per l'operatività quotidiana del caregiver.

**Independent Test**: Può essere testato richiedendo le informazioni di sistema e verificando che i dati ricevuti dall'appliance siano visualizzati correttamente.

**Acceptance Scenarios**:

1. **Given** un caregiver sul dettaglio OnesiBox, **When** accede alla sezione informazioni di sistema, **Then** vede i dati già disponibili dall'ultimo heartbeat (CPU, memoria, disco, temperatura)
2. **Given** un caregiver che visualizza le informazioni di sistema, **When** richiede un aggiornamento, **Then** viene inviato un comando per ottenere dati freschi inclusi uptime, load average e informazioni di rete
3. **Given** dati di sistema ricevuti, **When** il caregiver li visualizza, **Then** vede: uptime formattato (es. "2 giorni, 3 ore"), load average (1, 5, 15 min), RAM usata/totale (es. "1.2 GB / 4 GB"), CPU% attuale, disco usato/totale, IP, nome rete WiFi
4. **Given** una OnesiBox offline, **When** il caregiver visualizza le informazioni di sistema, **Then** vede i dati dell'ultimo heartbeat ricevuto con indicazione del timestamp
5. **Given** un caregiver con qualsiasi livello di permesso, **When** visualizza le informazioni di sistema, **Then** ha accesso ai dati (operazione di sola lettura)

---

### User Story 5 - Richiesta Log Applicazione (Priority: P3)

Il caregiver o l'admin può richiedere le ultime N righe di log dall'OnesiBox per diagnosticare problemi. Il numero di righe è configurabile (default 50, max 500). I log vengono restituiti e visualizzati nell'interfaccia.

**Why this priority**: L'accesso ai log è principalmente per diagnostica avanzata e troubleshooting, utile ma meno critico delle funzionalità operative.

**Independent Test**: Può essere testato richiedendo un numero specifico di righe di log e verificando che vengano restituite e visualizzate correttamente.

**Acceptance Scenarios**:

1. **Given** un caregiver o admin sul dettaglio OnesiBox, **When** accede alla sezione log, **Then** vede un form per specificare il numero di righe da recuperare
2. **Given** un caregiver che richiede 100 righe di log, **When** il comando viene eseguito, **Then** le righe di log vengono visualizzate in un'area scrollabile con formattazione appropriata
3. **Given** righe di log ricevute, **When** il caregiver le visualizza, **Then** ogni riga mostra timestamp, livello (info/warn/error) con colori distintivi, e messaggio
4. **Given** un caregiver che richiede più di 500 righe, **When** invia la richiesta, **Then** il sistema limita automaticamente a 500 righe
5. **Given** una OnesiBox offline, **When** il caregiver tenta di richiedere i log, **Then** riceve un messaggio che indica l'impossibilità di comunicare con l'appliance
6. **Given** un errore nell'appliance durante la lettura dei log, **When** il caregiver riceve la risposta, **Then** vede un messaggio di errore appropriato

---

### Edge Cases

- Cosa succede se l'OnesiBox cambia stato mentre il caregiver sta visualizzando i dettagli? → Lo stato viene aggiornato automaticamente tramite polling/WebSocket
- Cosa succede se un comando di volume viene inviato mentre l'appliance è in standby? → Il comando viene accettato e il volume sarà applicato alla prossima riproduzione
- Cosa succede se il caregiver elimina un comando che sta per essere prelevato dall'appliance? → Race condition gestita: se il comando è già stato prelevato (status != pending), l'eliminazione fallisce con messaggio appropriato
- Come gestire file di log molto grandi? → Limitazione a 500 righe massime, lettura da fine file (tail)
- Cosa succede se la rete WiFi non è disponibile ma l'appliance è connessa via Ethernet? → L'informazione WiFi mostra "Non connesso" o "N/D", mentre l'IP Ethernet viene mostrato

## Requirements *(mandatory)*

### Functional Requirements

#### Stato OnesiBox
- **FR-001**: Il sistema DEVE visualizzare lo stato operativo corrente dell'OnesiBox (idle, playing_video, playing_audio, calling_zoom)
- **FR-002**: Il sistema DEVE mostrare informazioni contestuali per ogni stato (URL/titolo media per playing, meeting ID per Zoom)
- **FR-003**: Il sistema DEVE aggiornare lo stato automaticamente entro 5 secondi da un cambiamento reale sull'appliance

#### Controllo Volume
- **FR-004**: Il sistema DEVE permettere la selezione del volume tra 5 livelli predefiniti: 20%, 40%, 60%, 80%, 100%
- **FR-005**: Il sistema DEVE inviare il comando set_volume all'appliance quando il caregiver seleziona un livello
- **FR-006**: Il sistema DEVE evidenziare visivamente il livello di volume corrente dell'appliance
- **FR-007**: Il sistema DEVE disabilitare i controlli volume per caregiver con permesso "ReadOnly"

#### Gestione Coda Comandi
- **FR-008**: Il sistema DEVE visualizzare la lista dei comandi pendenti per l'OnesiBox con tipo, data creazione, priorità
- **FR-009**: Il sistema DEVE permettere l'eliminazione di singoli comandi pendenti con conferma
- **FR-010**: Il sistema DEVE permettere l'eliminazione di tutti i comandi pendenti con conferma
- **FR-011**: Il sistema DEVE impedire l'eliminazione di comandi già in esecuzione o completati
- **FR-012**: Il sistema DEVE permettere ai caregiver "ReadOnly" di visualizzare la coda ma non di eliminare comandi

#### Informazioni di Sistema
- **FR-013**: Il sistema DEVE visualizzare le informazioni di sistema dall'ultimo heartbeat (CPU, memoria, disco, temperatura)
- **FR-014**: Il sistema DEVE permettere di richiedere informazioni aggiornate tramite un comando dedicato
- **FR-015**: L'OnesiBox DEVE rispondere con: uptime (formattato), load average (1/5/15 min), memoria (usata/totale), CPU%, disco (usato/totale), IP, nome rete WiFi
- **FR-016**: Il sistema DEVE mostrare il timestamp dell'ultimo aggiornamento dei dati di sistema

#### Log Applicazione
- **FR-017**: Il sistema DEVE permettere di richiedere le ultime N righe di log (default 50, max 500)
- **FR-018**: L'OnesiBox DEVE rispondere con le righe di log in formato strutturato (timestamp, livello, messaggio), filtrando automaticamente dati sensibili (password Zoom, token di autenticazione, credenziali)
- **FR-019**: Il sistema DEVE visualizzare i log con colori distintivi per livello (info, warn, error)
- **FR-020**: Il sistema DEVE gestire gli errori di lettura log mostrando messaggi appropriati

#### OnesiBox - Logging Migliorato
- **FR-021**: L'OnesiBox DEVE loggare tutte le richieste API ricevute con timestamp, tipo, e parametri rilevanti
- **FR-022**: L'OnesiBox DEVE loggare tutti i comandi in esecuzione con stato iniziale e risultato finale
- **FR-023**: L'OnesiBox DEVE loggare tutti gli errori con stack trace e contesto operativo
- **FR-024**: L'OnesiBox DEVE supportare un endpoint/comando per recuperare le ultime N righe di log

### Key Entities

- **OnesiBoxStatus**: Estensione dello stato esistente per includere informazioni contestuali sul media in riproduzione (url, titolo, tipo media) e sul meeting Zoom (meeting_id)
- **OnesiBoxCommand**: Comando esistente, esteso per permettere soft-delete/cancellazione da parte del caregiver prima dell'esecuzione
- **SystemInfo**: Struttura dati per le informazioni di sistema estese (uptime_seconds, uptime_formatted, load_average_1m/5m/15m, memory_used_bytes, memory_total_bytes, cpu_percent, disk_used_bytes, disk_total_bytes, ip_address, wifi_ssid, timestamp)
- **LogEntry**: Struttura dati per le righe di log (timestamp, level, message, context)

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Il caregiver visualizza lo stato aggiornato dell'OnesiBox entro 5 secondi da un cambiamento reale
- **SC-002**: Il 100% dei comandi volume inviati riceve feedback visivo entro 2 secondi
- **SC-003**: La lista comandi pendenti si carica entro 1 secondo
- **SC-004**: Le informazioni di sistema sono visualizzabili in formato leggibile (es. "2 GB / 4 GB" non "2147483648 / 4294967296")
- **SC-005**: I log richiesti vengono visualizzati entro 5 secondi dalla richiesta
- **SC-006**: L'interfaccia è completamente utilizzabile su smartphone (nessuno scroll orizzontale)
- **SC-007**: Tutti i controlli rispettano i permessi del caregiver (Full vs ReadOnly)
- **SC-008**: L'OnesiBox logga almeno il 95% delle operazioni significative in modo che siano tracciabili nei log

## Clarifications

### Session 2026-01-25

- Q: Qual è il comportamento desiderato per i log contenenti dati sensibili (password Zoom, token, ecc.)? → A: Filtrare automaticamente dati sensibili (password, token) prima di inviarli

## Assumptions

- Il sistema di heartbeat esistente (feature 002/003) viene esteso per includere informazioni aggiuntive sullo stato media
- La dashboard caregiver esistente (feature 004) viene estesa con i nuovi controlli
- Le API per i comandi esistono già (feature 003) e verranno estese per i nuovi tipi di comando
- L'OnesiBox utilizza Winston per il logging con file giornalieri in `/logs/`
- L'OnesiBox ha accesso a pipewire/PulseAudio per il controllo volume (tramite amixer o pactl)
- L'utente dell'OnesiBox ha permessi sudo per eseguire comandi di sistema
- Laravel Reverb è già configurato per gli aggiornamenti real-time

## Out of Scope

- Streaming live dei log (solo recupero on-demand)
- Storico dei cambiamenti di volume
- Grafici storici delle informazioni di sistema
- Notifiche push quando lo stato cambia
- Gestione di più livelli di volume personalizzati (solo i 5 predefiniti)
