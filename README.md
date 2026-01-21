# Onesiforo

[![Tests](https://github.com/onesiphorus-team/onesiforo-web/actions/workflows/tests.yml/badge.svg)](https://github.com/onesiphorus-team/onesiforo-web/actions/workflows/tests.yml)
[![PHPStan](https://github.com/onesiphorus-team/onesiforo-web/actions/workflows/phpstan.yml/badge.svg)](https://github.com/onesiphorus-team/onesiforo-web/actions/workflows/phpstan.yml)
[![Lint](https://github.com/onesiphorus-team/onesiforo-web/actions/workflows/lint.yml/badge.svg)](https://github.com/onesiphorus-team/onesiforo-web/actions/workflows/lint.yml)
[![Deploy](https://github.com/onesiphorus-team/onesiforo-web/actions/workflows/deploy-on-release.yml/badge.svg)](https://github.com/onesiphorus-team/onesiforo-web/actions/workflows/deploy-on-release.yml)

![PHP](https://img.shields.io/badge/PHP-8.4-777BB4?style=flat-square&logo=php&logoColor=white)
![Laravel](https://img.shields.io/badge/Laravel-12-FF2D20?style=flat-square&logo=laravel&logoColor=white)
![Livewire](https://img.shields.io/badge/Livewire-4-FB70A9?style=flat-square&logo=livewire&logoColor=white)
![Filament](https://img.shields.io/badge/Filament-5-FDAE4B?style=flat-square&logo=laravel&logoColor=white)
![Tailwind CSS](https://img.shields.io/badge/Tailwind_CSS-4-06B6D4?style=flat-square&logo=tailwindcss&logoColor=white)

---

## Panoramica

**Onesiforo** è un'applicazione web progettata per il controllo remoto di dispositivi OnesiBox, appliance hardware dedicate all'assistenza di persone anziane con mobilità ridotta. Il sistema consente ai caregiver di inviare contenuti multimediali, gestire videochiamate e monitorare lo stato dei dispositivi senza richiedere alcuna interazione da parte dell'utente finale.

Il nome deriva da Onesiforo, cristiano del I secolo che si distinse per la premura mostrata verso l'apostolo Paolo durante la sua prigionia a Roma (2 Timoteo 1:16-17).

## Scopo del Progetto

Il sistema nasce per rispondere a un'esigenza concreta: permettere a persone anziane sole di:

- Seguire contenuti spirituali edificanti da JW.org (video, cantici, discorsi)
- Partecipare alle adunanze dei Testimoni di Geova tramite Zoom
- Ricevere videochiamate dai propri cari e dalla congregazione
- Rimanere in contatto con la comunità senza dover interagire con alcun dispositivo

## Architettura del Sistema

Il sistema è composto da due componenti principali:

| Componente | Descrizione |
|------------|-------------|
| **Applicazione Web (questo repository)** | Pannello di controllo per i caregiver e API per le appliance |
| **OnesiBox (appliance)** | Dispositivo hardware basato su Raspberry Pi installato presso l'utente |

```
┌─────────────────┐         ┌─────────────────┐         ┌─────────────────┐
│    Caregiver    │────────▶│  Onesiforo Web  │◀────────│    OnesiBox     │
│  (Browser/App)  │         │   (Laravel 12)  │         │ (Raspberry Pi)  │
└─────────────────┘         └─────────────────┘         └─────────────────┘
                                    │
                            ┌───────┴───────┐
                            │               │
                      REST API        WebSocket
                      (Polling)    (Laravel Reverb)
```

## Funzionalita Principali

### Per il Caregiver

- **Riproduzione contenuti**: Invio di link audio/video da JW.org all'appliance
- **Gestione Zoom**: Avvio automatico di riunioni Zoom sull'appliance
- **Monitoraggio stato**: Visualizzazione in tempo reale dello stato dell'appliance
- **Cronologia**: Consultazione dello storico delle riproduzioni
- **Programmazione**: Impostazione di orari automatici per accensione/spegnimento
- **Text-to-Speech**: Invio di messaggi vocali letti ad alta voce dall'appliance
- **Videochiamata**: Avvio di sessioni video bidirezionali (Jitsi)
- **Accesso tecnico**: VNC reverse per assistenza remota

### Area Amministrativa (Filament)

- Gestione utenti caregiver
- Gestione appliance registrate
- Associazione caregiver-appliance
- Visualizzazione log di sistema
- Configurazione impostazioni generali

## Stack Tecnologico

| Categoria | Tecnologia |
|-----------|------------|
| Backend | PHP 8.4, Laravel 12 |
| Frontend Caregiver | Livewire 4, Tailwind CSS 4, Flux UI |
| Admin Panel | Filament 5 |
| WebSocket | Laravel Reverb, Laravel Echo |
| Database | SQLite (sviluppo), MySQL/PostgreSQL (produzione) |
| Testing | Pest 4, PHPUnit 12 |
| Analisi Statica | Larastan 3, Rector 2 |
| Code Style | Laravel Pint |
| Activity Log | spatie/laravel-activitylog |

## Requisiti di Sistema

- PHP >= 8.4
- Composer >= 2.0
- Node.js >= 20.x
- NPM >= 10.x

## Installazione

```bash
# Clona il repository
git clone https://github.com/onesiphorus-team/onesiforo-web.git
cd onesiforo-web

# Installa le dipendenze PHP
composer install

# Installa le dipendenze frontend
npm install

# Configura l'ambiente
cp .env.example .env
php artisan key:generate

# Esegui le migrazioni
php artisan migrate

# Compila gli asset
npm run build

# Avvia il server di sviluppo
composer run dev
```

## Comandi Utili

```bash
# Esegui i test
php artisan test

# Analisi statica
./vendor/bin/phpstan analyse

# Formattazione codice
./vendor/bin/pint

# Avvia Reverb (WebSocket server)
php artisan reverb:start
```

## Documentazione

La documentazione tecnica completa e disponibile nella cartella `/docs`:

- [Architettura del Sistema](docs/architettura.md)
- [Requisiti Funzionali e Non Funzionali](docs/requisiti.md)
- [Specifiche OnesiBox](docs/OnesiBox_Specifiche.pdf)

## Sicurezza

Il sistema implementa i massimi standard di sicurezza:

- Autenticazione a due fattori (2FA) per i caregiver
- Validazione rigorosa di tutti gli input
- Protezione CSRF su tutte le richieste
- Rate limiting sulle API
- Audit log di tutte le azioni sensibili
- Comunicazioni criptate (HTTPS/WSS)
- Token di autenticazione per le appliance

## Contribuire

Le contribuzioni sono benvenute. Prima di inviare una pull request:

1. Assicurati che tutti i test passino: `php artisan test`
2. Esegui l'analisi statica: `./vendor/bin/phpstan analyse`
3. Formatta il codice: `./vendor/bin/pint`
