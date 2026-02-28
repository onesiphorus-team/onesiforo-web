# Ambiente di Sviluppo con Docker

Questa guida ti accompagna nella configurazione dell'ambiente di sviluppo Docker per Onesiforo. L'obiettivo è: **clone, setup, lavora** — senza installare PHP, Composer, database o Redis sulla tua macchina.

## Indice

- [Prerequisiti](#prerequisiti)
  - [macOS](#macos)
  - [Windows](#windows)
  - [Linux](#linux)
- [Primo Avvio](#primo-avvio)
- [Uso Quotidiano](#uso-quotidiano)
- [Comandi Disponibili](#comandi-disponibili)
- [Accesso ai Servizi](#accesso-ai-servizi)
- [Lavorare con VS Code Dev Containers](#lavorare-con-vs-code-dev-containers)
- [Risoluzione Problemi](#risoluzione-problemi)

---

## Prerequisiti

Ti servono solo due cose: **Git** e un **container runtime** (Docker o OrbStack).

### macOS

**1. Git** (probabilmente lo hai gia)

```bash
git --version
# Se non c'e:
xcode-select --install
```

**2. OrbStack** (consigliato) oppure Docker Desktop

[OrbStack](https://orbstack.dev) e un'alternativa leggera e veloce a Docker Desktop per Mac. Usa meno RAM, si avvia in un secondo e ha un'interfaccia pulita. Include `docker` e `docker compose` come drop-in replacement.

```bash
# Installa OrbStack con Homebrew
brew install orbstack

# Oppure scaricalo da https://orbstack.dev/download
```

> Se preferisci Docker Desktop: [docker.com/products/docker-desktop](https://www.docker.com/products/docker-desktop/)

**3. make** (per i comandi rapidi)

```bash
make --version
# Se manca:
xcode-select --install
```

### Windows

**1. Git**

Scarica da [git-scm.com](https://git-scm.com/download/win) oppure:

```powershell
winget install Git.Git
```

**2. Docker Desktop**

Scarica da [docker.com/products/docker-desktop](https://www.docker.com/products/docker-desktop/). Richiede WSL 2 (Windows Subsystem for Linux).

> Durante l'installazione, Docker Desktop ti proporra di abilitare WSL 2 se non lo hai gia.

**3. make**

Su Windows `make` non c'e di default. Hai due opzioni:

- **Opzione A (consigliata)**: usa i comandi `docker compose` direttamente (sono elencati nella sezione [Comandi Disponibili](#comandi-disponibili))
- **Opzione B**: installa make tramite [Chocolatey](https://chocolatey.org/):
  ```powershell
  choco install make
  ```

> **Suggerimento**: lavora dentro WSL 2 (Ubuntu). Una volta dentro WSL, l'esperienza e identica a Linux e `make` e gia disponibile.

### Linux

**1. Git, Docker, make**

```bash
# Ubuntu / Debian
sudo apt update
sudo apt install git docker.io docker-compose-v2 make

# Aggiungi il tuo utente al gruppo docker (evita sudo)
sudo usermod -aG docker $USER
# Riavvia la sessione per applicare
```

```bash
# Fedora
sudo dnf install git docker docker-compose make
sudo systemctl enable --now docker
sudo usermod -aG docker $USER
```

```bash
# Arch Linux
sudo pacman -S git docker docker-compose make
sudo systemctl enable --now docker
sudo usermod -aG docker $USER
```

---

## Primo Avvio

```bash
# 1. Clona il repository
git clone https://github.com/onesiphorus-team/onesiforo-web.git
cd onesiforo-web

# 2. Avvia tutto
make setup
```

`make setup` fa tutto automaticamente:

1. Copia `.env.docker` in `.env` (solo se `.env` non esiste)
2. Genera l'`APP_KEY`
3. Builda le immagini Docker
4. Avvia tutti i container
5. Installa le dipendenze PHP (Composer) e Node (npm)
6. Esegue le migrazioni del database
7. Compila gli asset frontend

Al termine vedrai:

```
============================================
  Setup complete!
  App:     http://localhost:8000
  Vite:    http://localhost:5174
  Mailpit: http://localhost:8026
  Reverb:  ws://localhost:8085
============================================
```

Apri **http://localhost:8000** nel browser e sei pronto.

> **Senza make?** Esegui manualmente:
> ```bash
> cp .env.docker .env
> # Genera una APP_KEY (oppure fallo dopo con artisan)
> docker compose build
> docker compose up -d
> ```

---

## Uso Quotidiano

```bash
# Inizia a lavorare (avvia i container)
make up

# Finisci di lavorare (ferma i container, i dati restano)
make down

# Riavvia tutto (utile dopo modifiche a .env o Docker)
make restart

# Segui i log in tempo reale
make logs

# Log di un servizio specifico
make logs-app
make logs-queue
make logs-reverb
```

---

## Comandi Disponibili

Esegui `make help` per la lista completa. Ecco il riferimento:

### Gestione Ambiente

| Comando | Descrizione |
|---------|-------------|
| `make setup` | Primo avvio completo (build + up + install + migrate) |
| `make up` | Avvia tutti i container |
| `make down` | Ferma tutti i container (i dati persistono) |
| `make restart` | Riavvia tutti i container |
| `make build` | Ricostruisce le immagini Docker da zero |
| `make logs` | Mostra i log di tutti i servizi in tempo reale |
| `make logs-<servizio>` | Log di un servizio specifico (es. `make logs-app`) |
| `make clean` | Ferma tutto e **cancella tutti i dati** (volumi Docker) |

### Accesso al Container

| Comando | Descrizione |
|---------|-------------|
| `make shell` | Apre una shell nel container dell'app |
| `make tinker` | Apre Laravel Tinker (REPL PHP) |

### Test e Qualita del Codice

| Comando | Descrizione |
|---------|-------------|
| `make test` | Esegue tutti i test Pest |
| `make test-filter F=nomeTest` | Esegue solo i test che matchano il filtro |
| `make lint` | Esegue Pint sui file modificati |
| `make lint-fix` | Esegue Pint su tutti i file |
| `make analyse` | Esegue PHPStan (analisi statica) |

### Database

| Comando | Descrizione |
|---------|-------------|
| `make migrate` | Esegue le migrazioni pendenti |
| `make seed` | Esegue i seeder del database |
| `make fresh` | Ricrea il database da zero (migrate:fresh + seed) |

### Servizi

| Comando | Descrizione |
|---------|-------------|
| `make queue-restart` | Riavvia il queue worker |
| `make reverb-restart` | Riavvia il server WebSocket (Reverb) |

### Frontend

| Comando | Descrizione |
|---------|-------------|
| `make npm-install` | Installa le dipendenze Node.js |
| `make npm-build` | Compila gli asset per la produzione |

### Equivalenti senza make

Se non hai `make`, puoi usare `docker compose` direttamente:

| make | docker compose |
|------|----------------|
| `make up` | `docker compose up -d` |
| `make down` | `docker compose down` |
| `make shell` | `docker compose exec app sh` |
| `make test` | `docker compose exec app php artisan test --compact` |
| `make tinker` | `docker compose exec app php artisan tinker` |
| `make migrate` | `docker compose exec app php artisan migrate` |
| `make logs` | `docker compose logs -f` |
| `make clean` | `docker compose down -v --remove-orphans` |

---

## Accesso ai Servizi

Quando i container sono in esecuzione, i servizi sono raggiungibili a:

| Servizio | URL | Descrizione |
|----------|-----|-------------|
| **App** | http://localhost:8000 | Applicazione web Onesiforo |
| **Vite** | http://localhost:5174 | Dev server con Hot Module Replacement |
| **Mailpit** | http://localhost:8026 | Interfaccia web per le email di test |
| **Reverb** | ws://localhost:8085 | Server WebSocket |
| **MariaDB** | localhost:33060 | Database (user: `onesiforo`, password: `secret`) |
| **Redis** | localhost:63790 | Cache, sessioni, code |

> Le porte sono volutamente diverse da quelle standard per non entrare in conflitto con servizi locali (es. Laravel Herd, MySQL locale, ecc.).

### Connessione al Database con un client

Se usi TablePlus, DBeaver, DataGrip o simili:

| Parametro | Valore |
|-----------|--------|
| Host | `127.0.0.1` |
| Porta | `33060` |
| Database | `onesiforo` |
| Username | `onesiforo` |
| Password | `secret` |

---

## Lavorare con VS Code Dev Containers

Se usi VS Code, puoi sviluppare direttamente **dentro** il container con l'estensione [Dev Containers](https://marketplace.visualstudio.com/items?itemName=ms-vscode-remote.remote-containers).

1. Installa l'estensione **Dev Containers** in VS Code
2. Apri il progetto
3. VS Code rileva `.devcontainer/devcontainer.json` e propone: **"Reopen in Container"**
4. Clicca si — VS Code riaprira il progetto dentro il container `app`

Cosa ottieni automaticamente:

- PHP, Composer, Node disponibili nel terminale integrato
- Estensioni VS Code preconfigurate (Intelephense, Pint, Blade, Tailwind)
- Port forwarding automatico su tutti i servizi
- `postCreateCommand` esegue install + migrate al primo avvio

---

## Architettura dei Servizi

```
docker compose up -d
├── app          (FrankenPHP + PHP 8.4)   → :8000
├── vite         (Node 22)                → :5174  (HMR)
├── mariadb      (MariaDB 11)             → :33060
├── redis        (Redis 7)                → :63790
├── reverb       (PHP CLI)                → :8085  (WebSocket)
├── queue        (PHP CLI)                → queue worker
├── scheduler    (PHP CLI)                → cron scheduler
└── mailpit      (Mailpit)                → :8026  (email UI)
```

---

## Risoluzione Problemi

### `make: command not found`

Vedi la sezione [Prerequisiti](#prerequisiti) per il tuo sistema operativo.

### I test falliscono con errori di connessione al database

I test usano SQLite in-memory, non MariaDB. Se vedi errori di connessione, verifica che `phpunit.xml` abbia le variabili con `force="true"`.

### Errore "Vite manifest not found"

Il server Vite potrebbe non essere ancora pronto. Verifica:

```bash
make logs-vite
```

Se Vite non si avvia, prova:

```bash
make npm-install
make restart
```

### Le modifiche al codice non si riflettono

FrankenPHP potrebbe tenere in cache il codice. Riavvia il container:

```bash
make restart
```

### Errore su porta gia in uso

Se hai Laravel Herd o altri servizi in ascolto sulle stesse porte, puoi cambiarle creando un file `.env` nella root e sovrascrivendo:

```bash
# Cambia le porte nel .env prima di fare up
APP_PORT=8001
VITE_PORT=5175
DB_PORT=33061
```

### Reset completo (ricomincia da zero)

```bash
make clean   # Cancella container, volumi e dati
make setup   # Ricrea tutto da zero
```
