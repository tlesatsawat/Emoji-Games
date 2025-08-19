# Emoji Games Platform

This repository contains a full‑stack PHP and vanilla JavaScript project that implements a modular platform hosting a collection of mini‑games rendered using Unicode emoji.  The service exposes a RESTful API, a lightweight SDK for clients, a simple admin panel for approving payments and managing content, and a build configuration that runs on Koyeb's free tier using their PHP buildpack.

## Features

* **Account system** – users can register, log in and log out.  User data, inventory and avatar state are persisted in PostgreSQL.
* **Avatar system** – avatars are assembled from a base emoji and up to five accessory layers (head, eyes, mouth, hand and background).  Accessories can be purchased in the store or earned via gameplay.
* **Game engine** – the `/api/game/start` and `/api/game/submit` endpoints provide a common contract for all games.  Each game folder under `web/games/` contains HTML/CSS/JS code that uses the SDK to interact with the backend.
* **Leaderboards** – per‑game leaderboards are persisted for daily, weekly and all‑time periods.  Cron endpoints reset and summarise the leaderboards on a schedule.
* **Store and economy** – players earn coins through gameplay and can purchase boosters, themes or avatar accessories.  Gems are obtained via manual PromptPay payments and are used for premium items.
* **Admin panel** – administrators can approve or reject payment slips, toggle games on/off, and manage items and accessories.  Access is protected by the `ADMIN_EMAIL`/`ADMIN_PASS` credentials.
* **Cron jobs** – daily and weekly tasks are exposed as internal endpoints that reset leaderboards, distribute login bonuses and summarise ranks.  The included GitHub Actions work flows call these endpoints on a schedule.

## Getting started

### Prerequisites

* **PHP 8.2** with the `pdo` and `pdo_pgsql` extensions installed.  On most Unix systems you can install them via your package manager.
* **Composer** – used to install PHP dependencies and autoloading.  Download it from [getcomposer.org](https://getcomposer.org/) if you don't have it.
* **PostgreSQL** – either a local instance for development or a managed instance provided by Koyeb or Neon.  The application is designed to work with both.

### Setup

1. Clone this repository and change into the project directory.
2. Copy `.env.example` to `.env` and fill in the environment variables.  At minimum you must set:
   * `APP_URL` – the base URL of your deployment (e.g. `https://your-app.koyeb.app` or `http://127.0.0.1:8000` for local development).
   * `DB_URL` – the PostgreSQL connection URI, of the form `postgres://user:password@host:port/dbname`.
   * `SERVER_SECRET` and `SESSION_SECRET` – strong random strings used for HMAC calculation and session signing.
   * `ADMIN_EMAIL`/`ADMIN_PASS` – the credentials for the first administrator account.
3. Install dependencies with Composer:

   ```bash
   composer install
   ```

4. Run the database migrations to create all tables and indices:

   ```bash
   php scripts/migrate.php
   ```

5. Seed the database with an admin account, a sample user, game definitions and some default items and accessories:

   ```bash
   php scripts/seed.php
   ```

6. Start the application locally using PHP's built‑in server:

   ```bash
   php -S 127.0.0.1:8000 -t web
   ```

   You should now be able to visit `http://127.0.0.1:8000` in your browser.  Access the API at `/api/*` endpoints and games under `/games/<game>/`.

### Running tests

Unit and integration tests are located under the `tests/` directory.  To run them use PHPUnit:

```bash
./vendor/bin/phpunit
```

The test suite includes basic coverage for the HMAC utilities, nonce generation, authentication flow and a few API calls.  You can extend it as the project evolves.

### Deployment on Koyeb

This application is designed for a git‑driven deployment to [Koyeb](https://www.koyeb.com/).  Once your repository is ready:

1. Create a new Web Service on Koyeb and connect it to your GitHub repository.
2. Set the build pack to **PHP**.  The supplied `Procfile` instructs the build pack to serve the `web/` directory.
3. Add environment variables in the Koyeb dashboard matching those in your `.env` file.  When using Koyeb's managed PostgreSQL, copy the connection string into `DB_URL`.
4. Enable **auto‑deploy** so that pushes to your `main` branch automatically redeploy the service.

### Cron scheduling with GitHub Actions

Two workflow files under `.github/workflows/` (`daily.yml` and `weekly.yml`) call the internal cron endpoints on a schedule aligned with the Asia/Bangkok timezone.  To enable them:

1. Store the `CRON_TOKEN` secret in your GitHub repository secrets.
2. Verify the cron actions are enabled in your repository settings.
3. The workflows use `curl` to send authenticated POST requests to `/internal/cron/daily` and `/internal/cron/weekly`.

### Future work

This project lays the foundation for a large collection of mini‑games.  The provided game pages in `web/games/` are intentionally simple: each one loads the SDK, displays a countdown timer, tracks a score and submits results back to the server.  You can iterate on each game to add animations, sounds, increasing difficulty and polished visuals while still using the core API.

Multiplayer support, real‑time leaderboards and social features are possible extensions.  See the `docs/` directory for planning notes and architecture diagrams.
