# Feature Specification: Caregiver Dashboard

**Feature Branch**: `004-caregiver-dashboard`
**Created**: 2026-01-22
**Status**: Draft
**Input**: User description: "L'utente caregiver accede in /dashboard, vede quali appliance OnesiBox sono disponibili, sceglie l'appliance su cui lavorare. Viene mostrata una view con lo stato dell'appliance, cosa sta facendo e un riquadro con i contatti del recipient. In basso c'è una sezione per avviare le varie cose: riproduzione audio, video, Zoom. Form eleganti e precisi, applicazione ottimizzata per smartphone. TALL Stack con Livewire (file separati). DRY, SOLID, YAGNI e test ovunque."

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Visualizzazione lista OnesiBox (Priority: P1)

Il caregiver autenticato accede alla dashboard (/dashboard) e visualizza l'elenco delle appliance OnesiBox a cui ha accesso. Per ogni appliance vede: nome, stato online/offline, e lo stato corrente dell'attività (Idle, Playing, Calling, Error).

**Why this priority**: È la funzionalità fondamentale che permette al caregiver di vedere e selezionare le appliance. Senza questa, nessun'altra funzionalità è accessibile.

**Independent Test**: Può essere testato autenticando un caregiver e verificando che visualizzi correttamente le sue OnesiBox assegnate con i relativi stati.

**Acceptance Scenarios**:

1. **Given** un caregiver autenticato con 2 OnesiBox assegnate, **When** accede a /dashboard, **Then** visualizza una lista con entrambe le OnesiBox mostrando nome, stato connessione e stato attività
2. **Given** un caregiver autenticato senza OnesiBox assegnate, **When** accede a /dashboard, **Then** visualizza un messaggio informativo che indica l'assenza di appliance assegnate
3. **Given** un caregiver autenticato con OnesiBox, **When** una OnesiBox risulta offline da più di 5 minuti, **Then** viene visualizzato un indicatore visivo di stato offline

---

### User Story 2 - Dettaglio OnesiBox e Contatti Recipient (Priority: P2)

Il caregiver seleziona una OnesiBox dalla lista e visualizza una schermata dettagliata con: stato corrente dell'appliance (cosa sta facendo), informazioni di connessione, e un riquadro con i contatti del recipient associato (nome, telefono, indirizzo, contatti di emergenza).

**Why this priority**: Fornisce al caregiver le informazioni essenziali per assistere l'anziano e i suoi contatti in caso di necessità.

**Independent Test**: Può essere testato selezionando una OnesiBox e verificando che vengano mostrate tutte le informazioni del recipient e lo stato dell'appliance.

**Acceptance Scenarios**:

1. **Given** un caregiver che visualizza la lista OnesiBox, **When** seleziona una specifica appliance, **Then** viene mostrata la schermata di dettaglio con stato, ultimo heartbeat e contatti recipient
2. **Given** una OnesiBox senza recipient associato, **When** il caregiver accede al dettaglio, **Then** viene mostrato un messaggio che indica l'assenza di recipient
3. **Given** una OnesiBox con recipient che ha contatti di emergenza, **When** il caregiver visualizza il dettaglio, **Then** i contatti di emergenza sono visibili con nome, telefono e relazione
4. **Given** lo stato dell'appliance cambia (es. da Idle a Playing), **When** il caregiver visualizza il dettaglio, **Then** lo stato viene aggiornato in tempo reale senza refresh manuale

---

### User Story 3 - Controllo Riproduzione Audio (Priority: P3)

Il caregiver con permessi "Full" può avviare la riproduzione audio sull'OnesiBox selezionata. Il sistema presenta un form per selezionare o specificare il contenuto audio da riprodurre.

**Why this priority**: La riproduzione audio è una funzionalità core per comunicare con l'anziano o intrattenerlo.

**Independent Test**: Può essere testato avviando una riproduzione audio e verificando che il comando venga inviato all'appliance.

**Acceptance Scenarios**:

1. **Given** un caregiver con permesso "Full" sul dettaglio OnesiBox, **When** avvia riproduzione audio con contenuto valido, **Then** il comando viene inviato e lo stato passa a "Playing"
2. **Given** un caregiver con permesso "ReadOnly" sul dettaglio OnesiBox, **When** tenta di avviare riproduzione audio, **Then** l'azione non è disponibile/visibile
3. **Given** una OnesiBox offline, **When** il caregiver tenta di avviare riproduzione audio, **Then** viene mostrato un messaggio che indica l'impossibilità di comunicare con l'appliance
4. **Given** una riproduzione audio in corso, **When** il caregiver visualizza il dettaglio, **Then** può vedere che l'appliance è in stato "Playing"

---

### User Story 4 - Controllo Riproduzione Video (Priority: P4)

Il caregiver con permessi "Full" può avviare la riproduzione video sull'OnesiBox selezionata. Il sistema presenta un form per selezionare o specificare il contenuto video da riprodurre.

**Why this priority**: La riproduzione video amplia le possibilità di intrattenimento e comunicazione visiva.

**Independent Test**: Può essere testato avviando una riproduzione video e verificando che il comando venga inviato all'appliance.

**Acceptance Scenarios**:

1. **Given** un caregiver con permesso "Full" sul dettaglio OnesiBox, **When** avvia riproduzione video con contenuto valido, **Then** il comando viene inviato e lo stato passa a "Playing"
2. **Given** un caregiver con permesso "ReadOnly", **When** visualizza la sezione controlli, **Then** l'opzione video non è disponibile
3. **Given** una OnesiBox già in riproduzione, **When** il caregiver avvia un nuovo video, **Then** il contenuto precedente viene sostituito

---

### User Story 5 - Avvio Chiamata Zoom (Priority: P5)

Il caregiver con permessi "Full" può avviare una chiamata Zoom sull'OnesiBox selezionata. Il sistema presenta un form per inserire i parametri della chiamata (ID meeting, password se richiesta).

**Why this priority**: Le videochiamate sono essenziali per mantenere il contatto visivo tra familiari e anziano.

**Independent Test**: Può essere testato avviando una chiamata Zoom e verificando che il comando venga inviato all'appliance.

**Acceptance Scenarios**:

1. **Given** un caregiver con permesso "Full" sul dettaglio OnesiBox, **When** inserisce credenziali Zoom valide e avvia chiamata, **Then** il comando viene inviato e lo stato passa a "Calling"
2. **Given** un caregiver con permesso "ReadOnly", **When** visualizza la sezione controlli, **Then** l'opzione Zoom non è disponibile
3. **Given** una OnesiBox offline, **When** il caregiver tenta di avviare Zoom, **Then** viene mostrato un messaggio di errore appropriato
4. **Given** una chiamata Zoom in corso, **When** il caregiver visualizza il dettaglio, **Then** vede lo stato "Calling" e può terminare la chiamata

---

### Edge Cases

- Cosa succede quando l'OnesiBox va offline durante una sessione attiva? → Il sistema mostra uno stato di errore e disabilita i controlli
- Come gestisce il sistema le richieste concorrenti da più caregiver sulla stessa OnesiBox? → Le richieste vengono processate in ordine, l'ultima prevale
- Cosa succede se il recipient viene rimosso mentre il caregiver visualizza il dettaglio? → Il riquadro contatti mostra un messaggio di recipient non disponibile
- Come si comporta l'interfaccia su connessioni lente o instabili? → Indicatori di caricamento visibili e messaggi di errore chiari in caso di timeout

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Il sistema DEVE mostrare la lista delle OnesiBox assegnate al caregiver autenticato
- **FR-002**: Il sistema DEVE visualizzare lo stato di connessione (online/offline) per ogni OnesiBox
- **FR-003**: Il sistema DEVE visualizzare lo stato attività corrente (Idle, Playing, Calling, Error) per ogni OnesiBox
- **FR-004**: Il sistema DEVE permettere al caregiver di selezionare una OnesiBox per visualizzarne i dettagli
- **FR-005**: Il sistema DEVE mostrare i contatti del recipient associato all'OnesiBox (nome completo, telefono, indirizzo, contatti emergenza)
- **FR-006**: Il sistema DEVE aggiornare lo stato dell'OnesiBox in tempo reale senza refresh manuale
- **FR-007**: Il sistema DEVE permettere ai caregiver con permesso "Full" di avviare riproduzione audio
- **FR-008**: Il sistema DEVE permettere ai caregiver con permesso "Full" di avviare riproduzione video
- **FR-009**: Il sistema DEVE permettere ai caregiver con permesso "Full" di avviare chiamate Zoom
- **FR-010**: Il sistema DEVE impedire ai caregiver con permesso "ReadOnly" di inviare comandi all'appliance
- **FR-011**: Il sistema DEVE disabilitare i controlli quando l'OnesiBox è offline
- **FR-012**: Il sistema DEVE essere completamente utilizzabile su dispositivi smartphone (responsive design mobile-first)
- **FR-013**: Il sistema DEVE validare tutti gli input prima di inviare comandi all'appliance
- **FR-014**: Il sistema DEVE mostrare messaggi di feedback appropriati per ogni azione (successo, errore, caricamento)

### Key Entities

- **OnesiBox**: Appliance hardware che riceve comandi dal caregiver. Attributi chiave: nome, serial_number, stato connessione (derivato da last_seen_at), stato attività, recipient associato
- **Recipient**: Anziano assistito tramite l'OnesiBox. Attributi chiave: nome completo, telefono, indirizzo completo, contatti di emergenza (array con nome, telefono, relazione)
- **User (Caregiver)**: Utente che gestisce le OnesiBox. Relazione many-to-many con OnesiBox tramite pivot con permission (Full/ReadOnly)
- **OnesiBoxPermission**: Enum che definisce i livelli di accesso (Full: può inviare comandi, ReadOnly: può solo visualizzare)
- **OnesiBoxStatus**: Enum che definisce gli stati dell'appliance (Idle, Playing, Calling, Error)

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: I caregiver visualizzano la lista delle loro OnesiBox entro 2 secondi dall'accesso alla dashboard
- **SC-002**: Lo stato delle OnesiBox si aggiorna entro 3 secondi da un cambiamento reale sull'appliance
- **SC-003**: 100% delle operazioni di controllo (audio, video, Zoom) sono accessibili e completabili da smartphone senza scroll orizzontale
- **SC-004**: I form di controllo sono completabili con meno di 4 tap/click su smartphone
- **SC-005**: 95% dei comandi inviati ricevono feedback visivo entro 1 secondo
- **SC-006**: Tutti i test automatici (unit, feature, browser) passano prima del rilascio
- **SC-007**: Zero accessi non autorizzati a OnesiBox non assegnate al caregiver
- **SC-008**: Zero invii di comandi da caregiver con permesso ReadOnly

## Assumptions

- L'autenticazione utente è già implementata (Laravel Fortify)
- Il sistema di heartbeat delle OnesiBox è già funzionante (feature 002)
- Le relazioni tra User, OnesiBox e Recipient sono già definite nel database
- I comandi verso l'appliance saranno gestiti tramite un sistema di code/API che verrà definito nella fase di implementazione
- Il real-time sarà implementato tramite Laravel Reverb (già configurato nel progetto)
- I contenuti audio/video saranno specificati come URL o identificatori; la gestione del catalogo contenuti è fuori scope per questa feature

## Out of Scope

- Gestione catalogo contenuti audio/video
- Configurazione e registrazione nuove OnesiBox
- Gestione anagrafica recipient
- Storico comandi inviati
- Notifiche push su dispositivi mobili
- Supporto offline/PWA
