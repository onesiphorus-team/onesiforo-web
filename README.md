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

### Con Docker (consigliato)

L'ambiente Docker e il modo piu semplice per iniziare: non serve installare PHP, Composer, database o Redis.

```bash
git clone https://github.com/onesiphorus-team/onesiforo-web.git
cd onesiforo-web
make setup
```

Tutto qui. Apri http://localhost:8000 e sei pronto.

Consulta la **[Guida completa all'ambiente Docker](docs/setup-ambiente-docker.md)** per:
- Prerequisiti per macOS, Windows e Linux
- Lista di tutti i comandi disponibili
- Configurazione VS Code Dev Containers
- Risoluzione problemi

### Senza Docker (Laravel Herd / Valet)

Se preferisci un'installazione locale con [Laravel Herd](https://herd.laravel.com) o simili:

```bash
git clone https://github.com/onesiphorus-team/onesiforo-web.git
cd onesiforo-web

composer install
npm install

cp .env.example .env
php artisan key:generate
php artisan migrate

npm run build
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

## Deployment in Produzione

### Auto-Deploy

Il sistema utilizza **GitHub Actions** con un **self-hosted runner** per il deploy automatico.

**Il deploy viene attivato automaticamente quando:**

- Viene pubblicata una nuova **Release** su GitHub
- Viene pushato un **tag** con prefisso `v*` (es. `v1.0.0`, `v1.2.3`)

```bash
# Deploy tramite tag
git tag v1.0.0
git push origin v1.0.0

# Oppure crea una Release dalla UI di GitHub
# Repository → Releases → Create a new release
```

### Struttura Server

```
/var/www/onesiforo-web/
├── current/          → Symlink alla release attiva
├── releases/         → Storico delle release (ultime 5)
│   ├── 20260122_153824_main/
│   └── 20260122_160000_v1.0.0/
├── shared/           → File condivisi tra release
│   ├── .env
│   └── storage/
└── deploy.sh         → Script di deploy
```

### Servizi di Sistema

| Servizio | Descrizione | Comando |
|----------|-------------|---------|
| **Queue Worker** | Processa i job in coda | `onesiforo-web-worker.service` |
| **Scheduler** | Esegue i task schedulati ogni minuto | `onesiforo-web-scheduler.timer` |
| **PHP-FPM** | Processa le richieste PHP | `php8.4-fpm.service` |
| **Nginx** | Web server | `nginx.service` |
| **GitHub Runner** | Esegue i deploy automatici | `actions.runner.*.service` |

### Verifica Stato Servizi

```bash
# Connessione al server
ssh -p 65100 onesiforo-web

# Stato del worker
sudo systemctl status onesiforo-web-worker

# Stato dello scheduler
sudo systemctl status onesiforo-web-scheduler.timer

# Stato del runner GitHub
sudo systemctl status actions.runner.onesiphorus-team-onesiforo-web.onesiforo-web-runner
```

### Logs

```bash
# Log applicativo Laravel
tail -f /var/www/onesiforo-web/shared/storage/logs/laravel.log

# Log del worker
tail -f /var/www/onesiforo-web/shared/storage/logs/worker.log

# Log dello scheduler
tail -f /var/www/onesiforo-web/shared/storage/logs/scheduler.log

# Log di Nginx
sudo tail -f /var/log/nginx/onesiforo-web-error.log
```

### Rollback

In caso di problemi con una release, è possibile effettuare un rollback immediato:

```bash
# Lista delle release disponibili
ls -la /var/www/onesiforo-web/releases/

# Rollback a una release precedente
sudo ln -sfn /var/www/onesiforo-web/releases/<nome_release> /var/www/onesiforo-web/current

# Riavvia i servizi
sudo systemctl restart onesiforo-web-worker
sudo systemctl reload php8.4-fpm
```

### Deploy Manuale

Se necessario, è possibile eseguire un deploy manuale:

```bash
ssh -p 65100 onesiforo-web
sudo -u www-data /var/www/onesiforo-web/deploy.sh <tag_o_branch>

# Esempio
sudo -u www-data /var/www/onesiforo-web/deploy.sh v1.0.0
sudo -u www-data /var/www/onesiforo-web/deploy.sh main
```

### Riavvio Servizi

```bash
# Riavvia il worker (dopo modifiche ai job)
sudo systemctl restart onesiforo-web-worker

# Ricarica PHP-FPM (dopo modifiche alla configurazione)
sudo systemctl reload php8.4-fpm

# Riavvia Nginx
sudo systemctl reload nginx
```

## Documentazione

La documentazione tecnica completa e disponibile nella cartella `/docs`:

- [Ambiente di Sviluppo con Docker](docs/setup-ambiente-docker.md)
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
