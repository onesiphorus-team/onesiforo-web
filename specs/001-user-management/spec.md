# Feature Specification: Gestione Utenti e Ruoli

**Feature Branch**: `001-user-management`
**Created**: 2026-01-21
**Status**: Draft
**Input**: Comando per creare super-admin, risorsa Filament per gestione utenti con ruoli (super-admin, admin, caregiver), CRUD utenti, invio verifica email, reset password, inviti utente, activity log con spatie/activitylog

## Clarifications

### Session 2026-01-21

- Q: Modello ruoli utente: ruolo singolo o ruoli multipli? → A: Ruoli multipli tramite oltrematica/role-lite con trait HasRoles e tabella pivot. Un utente può avere più ruoli contemporaneamente (es. admin e caregiver).

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Creazione Super Admin Iniziale (Priority: P1)

Un tecnico esegue un comando da terminale per creare il primo utente super-admin del sistema. Questo utente avrà i massimi poteri e potrà gestire tutti gli altri utenti.

**Why this priority**: Senza un super-admin iniziale, nessuno può accedere al pannello di amministrazione. È il prerequisito fondamentale per tutte le altre funzionalità.

**Independent Test**: Può essere testato eseguendo il comando e verificando che l'utente super-admin venga creato correttamente nel sistema con tutte le autorizzazioni.

**Acceptance Scenarios**:

1. **Given** il sistema è appena installato e non esistono utenti, **When** il tecnico esegue il comando di creazione super-admin fornendo email e password, **Then** viene creato un utente con ruolo super-admin e email verificata automaticamente.
2. **Given** esiste già un utente nel sistema, **When** il tecnico esegue il comando di creazione super-admin, **Then** il comando funziona ugualmente e crea un nuovo super-admin.
3. **Given** il tecnico esegue il comando con una email già esistente, **When** il comando viene processato, **Then** viene mostrato un errore che indica che l'email è già in uso.

---

### User Story 2 - Accesso al Pannello Amministrazione (Priority: P1)

Un utente super-admin o admin accede al pannello di amministrazione Filament per gestire gli utenti del sistema. I caregiver non possono accedere a questo pannello.

**Why this priority**: L'accesso al pannello è necessario per poter utilizzare tutte le funzionalità di gestione utenti.

**Independent Test**: Può essere testato effettuando il login con diversi ruoli e verificando l'accesso o il rifiuto al pannello.

**Acceptance Scenarios**:

1. **Given** un utente con ruolo super-admin, **When** tenta di accedere al pannello Filament, **Then** l'accesso viene concesso e visualizza la dashboard.
2. **Given** un utente con ruolo admin, **When** tenta di accedere al pannello Filament, **Then** l'accesso viene concesso e visualizza la dashboard.
3. **Given** un utente con ruolo caregiver, **When** tenta di accedere al pannello Filament, **Then** l'accesso viene negato con messaggio appropriato.
4. **Given** un utente non autenticato, **When** tenta di accedere al pannello Filament, **Then** viene reindirizzato alla pagina di login.

---

### User Story 3 - Visualizzazione Lista Utenti (Priority: P1)

Un amministratore visualizza la lista di tutti gli utenti del sistema con informazioni essenziali: nome, cognome, email, stato verifica email e ultimo accesso.

**Why this priority**: La visualizzazione degli utenti è la base per tutte le operazioni di gestione.

**Independent Test**: Può essere testato accedendo alla risorsa utenti e verificando che le colonne mostrino i dati corretti.

**Acceptance Scenarios**:

1. **Given** un admin accede alla lista utenti, **When** la pagina viene caricata, **Then** vengono visualizzati nome, cognome, email, stato verifica email e ultimo accesso di ogni utente.
2. **Given** un utente non ha mai effettuato l'accesso, **When** la lista viene visualizzata, **Then** il campo ultimo accesso mostra un indicatore appropriato (es. "Mai").
3. **Given** un utente ha l'email verificata, **When** la lista viene visualizzata, **Then** viene mostrato un indicatore visivo positivo (es. badge verde).
4. **Given** un utente non ha l'email verificata, **When** la lista viene visualizzata, **Then** viene mostrato un indicatore visivo di attenzione (es. badge giallo/rosso).

---

### User Story 4 - Invito Nuovo Utente (Priority: P2)

Un amministratore invita un nuovo utente al sistema specificando nome, cognome, ruolo ed email. Il sistema invia automaticamente un'email con un link per impostare la password.

**Why this priority**: Permette di popolare il sistema con nuovi utenti in modo sicuro, senza che l'admin debba conoscere o impostare password.

**Independent Test**: Può essere testato creando un invito e verificando che l'email venga inviata con il link corretto.

**Acceptance Scenarios**:

1. **Given** un super-admin compila il form di invito con nome, cognome, email e ruolo admin, **When** conferma l'invito, **Then** viene creato un utente con quei dati e inviata email con link per impostare password.
2. **Given** un super-admin compila il form di invito con ruolo caregiver, **When** conferma l'invito, **Then** l'utente viene creato e l'email viene inviata.
3. **Given** un admin compila il form di invito, **When** seleziona il ruolo, **Then** può scegliere solo il ruolo caregiver (non admin o super-admin).
4. **Given** un admin tenta di invitare un utente con email già esistente, **When** conferma l'invito, **Then** viene mostrato un errore di validazione.
5. **Given** un utente riceve l'email di invito, **When** clicca sul link, **Then** viene portato a una pagina per impostare la propria password.

---

### User Story 5 - Modifica Dati Utente (Priority: P2)

Un amministratore modifica i dati anagrafici di un utente esistente (nome, cognome, email).

**Why this priority**: Necessario per correggere errori o aggiornare informazioni utente.

**Independent Test**: Può essere testato modificando i dati di un utente e verificando il salvataggio.

**Acceptance Scenarios**:

1. **Given** un admin visualizza la pagina di modifica di un utente, **When** modifica il nome e salva, **Then** le modifiche vengono salvate correttamente.
2. **Given** un admin modifica l'email di un utente con un'email già in uso, **When** tenta di salvare, **Then** viene mostrato un errore di validazione.

---

### User Story 6 - Gestione Ruoli Utente (Priority: P2)

Un amministratore assegna o rimuove ruoli agli utenti, rispettando i limiti del proprio ruolo.

**Why this priority**: Fondamentale per la sicurezza e la corretta segregazione dei permessi.

**Independent Test**: Può essere testato assegnando ruoli con diversi tipi di amministratore.

**Acceptance Scenarios**:

1. **Given** un super-admin visualizza un utente, **When** modifica il ruolo, **Then** può assegnare qualsiasi ruolo tra super-admin, admin e caregiver.
2. **Given** un admin visualizza un utente, **When** modifica il ruolo, **Then** può assegnare solo il ruolo caregiver.
3. **Given** un admin visualizza un utente con ruolo admin, **When** tenta di rimuovere il ruolo admin, **Then** l'operazione non è permessa.
4. **Given** un super-admin visualizza un utente con ruolo super-admin, **When** rimuove il ruolo, **Then** l'operazione viene completata.

---

### User Story 7 - Invio Email di Verifica (Priority: P2)

Un amministratore può inviare nuovamente l'email di verifica a un utente che non l'ha ancora verificata.

**Why this priority**: Utile quando l'utente non ha ricevuto o ha perso l'email originale.

**Independent Test**: Può essere testato inviando una nuova email di verifica e verificando la ricezione.

**Acceptance Scenarios**:

1. **Given** un admin visualizza un utente con email non verificata, **When** clicca su "Invia email di verifica", **Then** viene inviata una nuova email di verifica all'utente.
2. **Given** un admin visualizza un utente con email già verificata, **When** visualizza le azioni disponibili, **Then** l'azione "Invia email di verifica" non è disponibile o è disabilitata.
3. **Given** l'email di verifica viene inviata con successo, **When** l'operazione completa, **Then** viene mostrato un messaggio di conferma.

---

### User Story 8 - Invio Reset Password (Priority: P2)

Un amministratore può inviare un link per il reset della password a un utente.

**Why this priority**: Permette agli admin di aiutare utenti che hanno dimenticato la password.

**Independent Test**: Può essere testato inviando un reset password e verificando la ricezione dell'email.

**Acceptance Scenarios**:

1. **Given** un admin visualizza un utente, **When** clicca su "Invia reset password", **Then** viene inviata un'email con il link per reimpostare la password.
2. **Given** l'email di reset viene inviata, **When** l'utente clicca sul link, **Then** viene portato alla pagina per impostare una nuova password.
3. **Given** il link di reset password, **When** è trascorsa più di 1 ora, **Then** il link non è più valido.

---

### User Story 9 - Eliminazione Utente (Priority: P3)

Solo un super-admin può eliminare utenti dal sistema. L'eliminazione avviene prima in modalità soft-delete, poi può essere definitiva (force-delete).

**Why this priority**: Funzionalità di sicurezza che deve essere limitata al massimo livello di autorizzazione.

**Independent Test**: Può essere testato tentando l'eliminazione con diversi ruoli.

**Acceptance Scenarios**:

1. **Given** un super-admin visualizza un utente diverso da sé stesso, **When** clicca su "Elimina", **Then** l'utente viene soft-deleted (non più visibile ma recuperabile).
2. **Given** un super-admin visualizza il proprio profilo, **When** cerca l'opzione elimina, **Then** l'opzione non è disponibile (non può eliminare sé stesso).
3. **Given** un admin visualizza un utente, **When** cerca l'opzione elimina, **Then** l'opzione non è disponibile.
4. **Given** un super-admin visualizza gli utenti eliminati (soft-deleted), **When** seleziona un utente eliminato, **Then** può scegliere di ripristinarlo o eliminarlo definitivamente (force-delete).
5. **Given** un utente viene eliminato definitivamente, **When** l'operazione completa, **Then** tutti i suoi dati vengono rimossi dal sistema in modo irreversibile.

---

### User Story 10 - Visualizzazione Activity Log (Priority: P3)

Un amministratore può visualizzare il log di tutte le attività degli utenti nel sistema.

**Why this priority**: Importante per audit e sicurezza, ma non blocca le funzionalità core.

**Independent Test**: Può essere testato eseguendo azioni e verificando che vengano registrate nel log.

**Acceptance Scenarios**:

1. **Given** un admin accede alla sezione Activity Log, **When** la pagina viene caricata, **Then** vengono visualizzate le attività recenti con data, utente, azione e dettagli.
2. **Given** un utente viene creato, modificato o eliminato, **When** l'azione viene completata, **Then** viene registrata nel log delle attività.
3. **Given** un admin visualizza il log, **When** filtra per utente specifico, **Then** vengono mostrate solo le attività relative a quell'utente.
4. **Given** un admin visualizza il log, **When** filtra per tipo di azione, **Then** vengono mostrate solo le attività di quel tipo.

---

### Edge Cases

- Cosa succede se l'unico super-admin tenta di eliminare sé stesso? L'operazione deve essere bloccata.
- Cosa succede se l'unico super-admin viene soft-deleted da un altro super-admin? Deve esistere sempre almeno un super-admin attivo.
- Cosa succede se un utente tenta di accedere con un link di reset password già utilizzato? Deve essere mostrato un errore appropriato.
- Cosa succede se l'email di invito viene inviata ma l'utente non la riceve? L'admin può reinviare l'invito.
- Cosa succede se un utente invitato non completa la registrazione entro un certo tempo? Il link scade dopo 7 giorni, l'admin può reinviare l'invito.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Il sistema DEVE fornire un comando da terminale per creare un utente super-admin con email e password.
- **FR-002**: Il sistema DEVE supportare tre ruoli: super-admin, admin e caregiver.
- **FR-003**: Il sistema DEVE impedire l'accesso al pannello Filament agli utenti con ruolo caregiver.
- **FR-004**: Il sistema DEVE permettere solo ai super-admin di assegnare/rimuovere ruoli admin e super-admin.
- **FR-005**: Il sistema DEVE permettere agli admin di assegnare/rimuovere solo il ruolo caregiver.
- **FR-006**: Il sistema DEVE permettere solo ai super-admin di eliminare utenti (soft-delete e force-delete).
- **FR-007**: Il sistema DEVE impedire a un utente di eliminare sé stesso.
- **FR-008**: Il sistema DEVE garantire che esista sempre almeno un super-admin attivo.
- **FR-009**: Il sistema DEVE permettere agli admin di modificare nome, cognome ed email degli utenti.
- **FR-010**: Il sistema DEVE permettere agli admin di inviare email di verifica agli utenti con email non verificata.
- **FR-011**: Il sistema DEVE permettere agli admin di inviare link di reset password agli utenti.
- **FR-012**: Il sistema DEVE fornire un'action per invitare nuovi utenti specificando nome, cognome, ruolo ed email.
- **FR-013**: Il sistema DEVE inviare automaticamente un'email di invito con link per impostare la password.
- **FR-014**: I link di reset password e invito DEVONO scadere dopo un periodo definito (reset: 1 ora, invito: 7 giorni).
- **FR-015**: La tabella utenti DEVE mostrare: nome, cognome, email, stato verifica email, ultimo accesso.
- **FR-016**: Il sistema DEVE registrare tutte le attività utente tramite activity log.
- **FR-017**: Il sistema DEVE fornire una vista per visualizzare e filtrare le attività registrate.
- **FR-018**: Il sistema DEVE mostrare indicatori visivi chiari per lo stato di verifica email.

### Key Entities

- **User**: Rappresenta un utente del sistema. Attributi principali: nome, cognome, email, password (hash), stato verifica email, ultimo accesso, data creazione, data eliminazione (soft-delete). Utilizza il trait HasRoles di oltrematica/role-lite.
- **Role**: Rappresenta un ruolo nel sistema (super-admin, admin, caregiver). Un utente può avere più ruoli contemporaneamente (es. admin e caregiver). Relazione many-to-many tramite tabella pivot.
- **Activity**: Rappresenta un'attività registrata nel log. Attributi: utente che ha eseguito l'azione, tipo di azione, soggetto dell'azione, dettagli, timestamp.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Il comando di creazione super-admin completa in meno di 5 secondi.
- **SC-002**: Gli amministratori possono invitare un nuovo utente in meno di 1 minuto (3 click massimo).
- **SC-003**: Il 100% delle azioni di creazione, modifica ed eliminazione utenti viene registrato nel log.
- **SC-004**: Gli utenti invitati possono completare la configurazione del proprio account in meno di 2 minuti dal click sul link.
- **SC-005**: La lista utenti si carica in meno di 2 secondi con fino a 1000 utenti.
- **SC-006**: Il sistema impedisce correttamente tutte le operazioni non autorizzate basate sui ruoli (0 violazioni di autorizzazione).
- **SC-007**: Il 100% delle email di invito e reset password viene inviato entro 30 secondi dalla richiesta.

## Assumptions

- Il sistema utilizza Laravel Fortify per la gestione dell'autenticazione e reset password.
- Spatie Activity Log è già installato o verrà installato come dipendenza.
- L'invio email è configurato correttamente nell'ambiente.
- La gestione ruoli utilizza oltrematica/role-lite con trait HasRoles e tabella pivot per supportare ruoli multipli per utente.
- Il soft-delete utilizza il trait SoftDeletes di Laravel.
- Per l'accesso a Filament, è sufficiente avere almeno uno dei ruoli super-admin o admin (il ruolo con permessi più elevati prevale).
