# Feature Specification: Sessioni Video a Tempo con Playlist

**Feature Branch**: `010-timed-playlist-sessions`
**Created**: 2026-02-05
**Status**: Draft
**Input**: Il caregiver vuole avviare sessioni di riproduzione video automatica con durata predefinita sulla OnesiBox, così il beneficiario può godersi i contenuti senza dover interagire o chiedere aiuto a qualcuno per fermare la riproduzione.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Avvio sessione con playlist manuale (Priority: P1)

Il caregiver vuole far vedere al beneficiario una serie di video specifici. Accede alla dashboard, inserisce una lista di URL video (ad esempio da JW.org), seleziona una durata massima per la sessione (30 minuti, 1 ora, 2 ore o 3 ore) e avvia la sessione. La OnesiBox inizia a riprodurre i video uno dopo l'altro automaticamente. Quando il tempo della sessione scade, la riproduzione si ferma da sola. Se tutti i video della lista finiscono prima dello scadere del tempo, la riproduzione si ferma.

**Why this priority**: Questa è la funzionalità core che risolve il problema principale: il beneficiario non deve più chiamare qualcuno per avviare nuovi video e non deve preoccuparsi di fermare la riproduzione. Il caregiver ha il pieno controllo dei contenuti e della durata.

**Independent Test**: Può essere testato creando una playlist con 2-3 URL video, impostando una durata di sessione, e verificando che i video vengano riprodotti in sequenza e che la sessione si fermi allo scadere del tempo.

**Acceptance Scenarios**:

1. **Given** il caregiver è nella dashboard con una OnesiBox online, **When** inserisce 3 URL video e seleziona "1 ora" come durata, **Then** la sessione viene creata e il primo video inizia a riprodursi sulla OnesiBox.
2. **Given** una sessione è in corso e il video corrente termina, **When** ci sono ancora video nella playlist e il tempo non è scaduto, **Then** il video successivo inizia automaticamente.
3. **Given** una sessione è in corso, **When** il tempo massimo della sessione scade durante la riproduzione di un video, **Then** il video corrente viene lasciato finire, dopodiché la riproduzione si ferma automaticamente e la OnesiBox torna in stato idle.
4. **Given** una sessione è in corso, **When** tutti i video della playlist sono stati riprodotti ma il tempo non è ancora scaduto, **Then** la riproduzione si ferma e la sessione termina.
5. **Given** il caregiver ha avviato una sessione, **When** decide di fermarla prima dello scadere del tempo, **Then** può interrompere la sessione manualmente dalla dashboard.

---

### User Story 2 - Sessione da sezione JW.org (Priority: P2)

Il caregiver vuole far vedere al beneficiario una serie di video da una sezione specifica di JW.org (ad esempio "Adorazione Mattutina", "Video Interviste", o altre sezioni tematiche). Inserisce l'URL della sezione, seleziona una durata e avvia la sessione. Il sistema estrae automaticamente i video disponibili dalla sezione e li riproduce in sequenza sulla OnesiBox fino allo scadere del tempo.

**Why this priority**: Questa funzionalità è un'evoluzione naturale della playlist manuale. Riduce il lavoro del caregiver che non deve cercare e copiare singolarmente ogni URL video. Tuttavia, dipende dalla possibilità di estrarre i video da JW.org, il che introduce complessità aggiuntiva.

**Independent Test**: Può essere testato inserendo l'URL di una sezione JW.org, verificando che il sistema estragga correttamente i video, e che la sessione si comporti come una playlist manuale.

**Acceptance Scenarios**:

1. **Given** il caregiver è nella dashboard, **When** inserisce un URL di sezione JW.org valido e seleziona "2 ore", **Then** il sistema estrae i video dalla sezione e avvia la sessione.
2. **Given** il sistema sta estraendo i video da una sezione JW.org, **When** l'estrazione è completata, **Then** il caregiver può vedere quanti video sono stati trovati prima di confermare l'avvio.
3. **Given** l'URL della sezione JW.org non è valido o non contiene video, **When** il caregiver tenta di avviare la sessione, **Then** il sistema mostra un messaggio di errore chiaro.

---

### User Story 3 - Monitoraggio sessione in corso (Priority: P2)

Il caregiver vuole vedere lo stato della sessione in corso: quale video sta riproducendo la OnesiBox, quanti video sono rimasti, quanto tempo manca alla fine della sessione. Vuole anche poter interrompere la sessione in qualsiasi momento.

**Why this priority**: Il monitoraggio dà al caregiver tranquillità e controllo. Senza questa funzionalità, il caregiver non sa se la sessione sta funzionando correttamente o se c'è stato un problema.

**Independent Test**: Può essere testato avviando una sessione e verificando che la dashboard mostri correttamente le informazioni di progresso e che il pulsante di interruzione funzioni.

**Acceptance Scenarios**:

1. **Given** una sessione è in corso, **When** il caregiver accede alla dashboard, **Then** vede il titolo/URL del video in riproduzione, il numero del video corrente rispetto al totale, e il tempo rimanente della sessione.
2. **Given** una sessione è in corso, **When** il caregiver clicca "Interrompi sessione", **Then** la riproduzione si ferma e la OnesiBox torna in stato idle.
3. **Given** una sessione termina (per scadenza tempo o fine playlist), **When** il caregiver accede alla dashboard, **Then** vede che la sessione è terminata e un riepilogo (video riprodotti, durata effettiva).

---

### User Story 4 - Playlist salvate e riutilizzabili (Priority: P3)

Il caregiver crea spesso le stesse playlist (ad esempio "Video del mattino per Rosa"). Vuole poter salvare una playlist con un nome e riutilizzarla in futuro senza dover reinserire tutti gli URL ogni volta.

**Why this priority**: Migliora l'esperienza del caregiver riducendo il lavoro ripetitivo, ma non è essenziale per il funzionamento base della feature. Le prime tre user story forniscono già valore completo.

**Independent Test**: Può essere testato creando una playlist, salvandola con un nome, e verificando che possa essere ricaricata e riutilizzata per avviare una nuova sessione.

**Acceptance Scenarios**:

1. **Given** il caregiver ha inserito una lista di URL video, **When** clicca "Salva playlist" e assegna un nome, **Then** la playlist viene salvata e appare nella lista delle playlist disponibili.
2. **Given** il caregiver ha playlist salvate, **When** seleziona una playlist esistente, **Then** gli URL vengono caricati automaticamente e può avviare una sessione.
3. **Given** il caregiver ha una playlist salvata, **When** vuole modificarla (aggiungere/rimuovere video o cambiare ordine), **Then** può farlo e salvare le modifiche.

---

### Edge Cases

- Cosa succede se la OnesiBox va offline durante una sessione? Il timer della sessione continua a scorrere sul backend. Quando la OnesiBox torna online e chiede il prossimo video, il backend valuta il tempo rimanente e risponde di conseguenza. Se il tempo è scaduto, la sessione risulta terminata.
- Cosa succede se un video della playlist non è più disponibile o ha un errore di riproduzione? Il sistema salta al video successivo e registra l'errore.
- Cosa succede se il caregiver tenta di avviare una sessione mentre un'altra è già in corso? Il sistema chiede conferma per sostituire la sessione corrente.
- Cosa succede se la playlist contiene un solo video più corto della durata della sessione? Il video viene riprodotto una volta e la sessione termina.
- Cosa succede se la sezione JW.org cambia struttura o non è raggiungibile? Il sistema mostra un errore chiaro e suggerisce di usare la playlist manuale.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: Il sistema DEVE permettere al caregiver di creare una sessione video specificando una lista di URL video e una durata massima.
- **FR-002**: Il sistema DEVE supportare le seguenti durate di sessione: 30 minuti, 1 ora, 2 ore, 3 ore.
- **FR-003**: La OnesiBox DEVE riprodurre i video della playlist in ordine sequenziale, passando automaticamente al successivo quando il corrente termina.
- **FR-004**: La OnesiBox DEVE richiedere il prossimo video al backend quando il video corrente termina, invece di ricevere l'intera playlist in anticipo. La OnesiBox non DEVE contenere alcuna logica di gestione sessione o timer: tutta la logica di durata e sequenza risiede nel backend.
- **FR-005**: Il backend DEVE verificare il tempo rimanente della sessione quando la OnesiBox richiede il prossimo video. Se il tempo è scaduto, DEVE rispondere indicando che la sessione è terminata. Il video in riproduzione al momento della scadenza DEVE essere lasciato finire prima che la sessione si concluda.
- **FR-006**: Il sistema DEVE permettere al caregiver di interrompere una sessione in corso in qualsiasi momento.
- **FR-007**: Il sistema DEVE mostrare al caregiver lo stato della sessione in corso (video corrente, progresso, tempo rimanente).
- **FR-008**: Il sistema DEVE gestire gli errori di riproduzione saltando al video successivo nella playlist.
- **FR-009**: Il sistema DEVE validare gli URL video inseriti dal caregiver prima di avviare la sessione.
- **FR-010**: Il sistema DEVE permettere al caregiver di inserire un URL di una sezione JW.org e estrarne automaticamente i video disponibili.
- **FR-011**: Il sistema DEVE mostrare al caregiver il numero di video estratti dalla sezione JW.org prima di confermare l'avvio della sessione.
- **FR-012**: Il sistema DEVE impedire l'avvio di una nuova sessione se una è già in corso, chiedendo conferma per sostituirla.
- **FR-013**: Il sistema DEVE permettere al caregiver di salvare una playlist con un nome per riutilizzarla in futuro. La playlist è associata alla OnesiBox e visibile a tutti i caregiver con permesso "full".
- **FR-014**: Il sistema DEVE permettere al caregiver di modificare e eliminare le playlist salvate della OnesiBox.
- **FR-015**: Solo i caregiver con permesso "full" DEVONO poter avviare, interrompere sessioni e gestire playlist. I caregiver con permesso "read-only" possono vedere lo stato della sessione in corso ma non modificarla.

### Key Entities

- **Session (Sessione)**: Rappresenta una sessione di riproduzione a tempo. Attributi: OnesiBox associata, durata massima, ora di inizio, stato (attiva, completata, interrotta, in errore), video corrente nella sequenza.
- **Playlist**: Collezione ordinata di URL video con un nome opzionale. Attributi: nome, lista ordinata di video (URL e titolo opzionale), OnesiBox associata. Le playlist salvate sono condivise tra tutti i caregiver con permesso "full" sulla stessa OnesiBox.
- **Playlist Item**: Singolo video nella playlist. Attributi: URL, titolo (opzionale), posizione nell'ordine, stato di riproduzione (non riprodotto, in riproduzione, completato, errore).

## Clarifications

### Session 2026-02-05

- Q: Quando il tempo della sessione scade e un video è in riproduzione, il sistema deve fermarsi immediatamente o lasciare finire il video corrente? → A: Finisce il video corrente, poi si ferma. La sessione può durare qualche minuto in più rispetto alla durata impostata.
- Q: Cosa succede se la OnesiBox va offline durante una sessione? → A: Il timer della sessione continua a scorrere sul backend. Quando la OnesiBox torna online e chiede il prossimo video, il backend valuta il tempo rimanente. Se è scaduto, la sessione è terminata. La OnesiBox non gestisce alcuna logica di sessione: chiede il prossimo video, il backend decide.
- Q: Le playlist salvate sono personali per caregiver o condivise tra i caregiver della stessa OnesiBox? → A: Condivise per OnesiBox. Tutti i caregiver con permesso "full" vedono le stesse playlist salvate, le sessioni in corso e il relativo stato.

## Assumptions

- Gli URL video inseriti dal caregiver sono URL diretti a contenuti riproducibili (MP4, video JW.org embeddable, ecc.).
- La OnesiBox ha già la capacità di riprodurre video da URL tramite il comando `play_media` esistente.
- L'estrazione dei video da sezioni JW.org è tecnicamente fattibile tramite scraping o API del sito.
- Una sola sessione alla volta può essere attiva per OnesiBox.
- Il beneficiario non ha bisogno di interagire in alcun modo con la OnesiBox durante una sessione.
- La connessione tra OnesiBox e backend è sufficientemente stabile per il polling dei prossimi video (attualmente polling ogni 5 secondi).

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Il caregiver può avviare una sessione video con playlist in meno di 2 minuti.
- **SC-002**: La transizione tra un video e il successivo avviene entro 10 secondi dalla fine del video precedente.
- **SC-003**: La sessione si ferma automaticamente entro 30 secondi dallo scadere del tempo impostato.
- **SC-004**: Il 100% delle sessioni avviate con URL validi riproduce correttamente almeno il primo video.
- **SC-005**: Il caregiver può monitorare lo stato della sessione in tempo reale con aggiornamenti entro 30 secondi.
- **SC-006**: Il beneficiario non deve compiere nessuna azione per l'intera durata della sessione.
- **SC-007**: Riduzione del numero di interazioni manuali del caregiver per la riproduzione video di almeno il 80% rispetto al flusso attuale (un comando per sessione invece di un comando per video).
