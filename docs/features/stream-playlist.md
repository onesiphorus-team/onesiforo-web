# Stream Playlist (JW Stream)

Emissione del comando `play_stream_item` verso un OnesiBox per riprodurre il video N-esimo di una playlist su `https://stream.jw.org/`.

## Prerequisiti

- OnesiBox client aggiornato alla versione che include il comando `play_stream_item` (client repo `onesi-box`, branch `feature/play-stream-item` o successive).
- Operatore autorizzato con permesso `control` sul dispositivo.
- OnesiBox online (vedi indicatore online nel dashboard).

## Flusso operativo (UI)

1. Dashboard admin → seleziona l'OnesiBox target (pagina detail del dispositivo).
2. Individua il pannello "Stream Playlist (JW Stream)".
3. Inserisci l'URL di share della playlist (es. `https://stream.jw.org/6311-4713-5379-2156`).
4. Clicca **Avvia playlist** → parte il primo video, il pannello mostra "Video corrente: 1".
5. Per passare al video successivo, clicca **Successivo** → il client OnesiBox chiude il video corrente, ri-naviga, avvia il video N+1.
6. Per tornare al precedente, clicca **Precedente**.
7. Quando raggiungi l'ultimo video della playlist, premendo ancora **Successivo** apparirà il banner verde "Ultimo video della playlist raggiunto" e il bottone verrà disabilitato. Per tornare indietro, premi **Precedente**.
8. Per fermare la riproduzione e tornare allo standby, clicca **Stop**.

## Comportamento dopo refresh della pagina

Il pannello ricostruisce lo stato (URL + ordinale + eventuale fine playlist) dai comandi inviati nelle ultime 6 ore. Se l'ultimo comando supera le 6 ore, il pannello si presenta vuoto.

## Errori possibili

| Banner | Codice | Cosa fare |
|---|---|---|
| Rosso: "Impossibile raggiungere JW Stream" | E110 | Verifica connessione internet del dispositivo |
| Rosso: "Playlist non caricata" | E111 | L'URL potrebbe essere scaduto/errato — chiedere nuovo share link |
| Verde: "Ultimo video della playlist raggiunto" | E112 | Informativo, non è un errore |
| Giallo: "Impossibile avviare il video" | E113 | Il sito JW Stream potrebbe essere cambiato — riprovare o segnalare supporto |
| Rosso: "OnesiBox non raggiungibile" | offline | Verifica il dispositivo è acceso e online |

## Limitazioni note

- **Solo share link pubblici**: URL che richiedono login personale JW non sono supportati.
- **Gap di 5-10 secondi tra un video e l'altro**: l'OnesiBox deve ri-navigare alla SPA JW Stream e cliccare il nuovo tile. Inevitabile con questo approccio DOM-driven.
- **Massimo 50 video per playlist** (limite di validazione).
- **Se JW Stream cambia struttura DOM**: il comando fallisce pulito con errore E111. Richiede aggiornamento firmware OnesiBox.

## Smoke test post-deploy

1. Accedi alla dashboard admin con utente autorizzato.
2. Seleziona un OnesiBox di test online (dev o staging).
3. Copia un share link valido (es. un'assemblea recente dal pannello JW Stream).
4. Inserisci l'URL nel pannello Stream Playlist → **Avvia playlist**.
5. Verifica sul dispositivo: navigazione automatica → primo video in playback fullscreen.
6. Clicca **Successivo** → secondo video dopo ~5-10s.
7. Clicca **Precedente** → primo video.
8. Clicca **Successivo** più volte oltre l'ultimo: banner verde appare, bottone disabilitato.
9. Clicca **Stop**: dispositivo torna in standby.
10. Refresh browser: stato conservato (URL + ordinale corretto).
