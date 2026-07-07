# Crow CLI

Crow CLI is a standalone command-line companion for Crow developer handoffs. It reads queued Crow events, listens for live forwarded events, and fetches implementation-plan handoffs in formats that are ready to paste into an AI coding agent or use directly in a terminal workflow.

The app is built with [Laravel Zero](https://laravel-zero.com/) and ships as a PHAR-backed `crow` executable.

## Installation

Once the package is published, install it globally with Composer:

```bash
composer global require crowbot/cli
```

Make sure Composer's global bin directory is on your `PATH`, then verify the install:

```bash
crow list
```

For local development from this repository:

```bash
composer install
php crow list
```

The checked-in PHAR build is available at:

```bash
./builds/crow list
```

## Authentication

Run the login command and paste a Crow API token:

```bash
crow auth login
```

Credentials are stored at:

```text
~/.crow/config.json
```

The config file is written with restrictive permissions where the platform supports it. Environment variables remain supported for automation and CI.

Configuration precedence is:

1. Explicit command options, such as `--api-token` or `--api-url`
2. Environment variables
3. `~/.crow/config.json`
4. Built-in defaults

Supported environment variables:

```bash
CROW_API_URL=https://crow.test/api/v1
CROW_API_TOKEN=your_token_here
CROW_APP_ID=
CROW_LISTEN_PUBLIC_URL=
CROW_LISTEN_HOST=127.0.0.1
CROW_LISTEN_PORT=8787
CROW_LISTEN_SECRET=
```

The current default API URL is `https://crow.test/api/v1`.

## Commands

### Fetch Implementation Plans

List active implementation plans:

```bash
crow plan
```

Fetch a specific plan handoff:

```bash
crow plan <plan-id>
```

Print raw JSON:

```bash
crow plan <plan-id> --json
```

Write output to a file:

```bash
crow plan <plan-id> --output=handoff.md
```

### Read Crow Events

Read the latest unread event:

```bash
crow read
```

Read a specific event:

```bash
crow read <event-id>
```

Leave the event unread after printing:

```bash
crow read <event-id> --leave-unread
```

Filter unread lookup by app or event types:

```bash
crow read --app-id=123 --events=dispatch.received --events=recon.ready
```

### Listen For Live Events

Start a local listener:

```bash
crow listen --public-url=https://your-public-url.example
```

The listener binds to `127.0.0.1:8787` by default and receives events at:

```text
POST /crow/events
GET /health
```

Override the bind address:

```bash
crow listen --host=127.0.0.1 --port=8787
```

Start the listener without registering it with Crow:

```bash
crow listen --no-register
```

When registering with Crow, expose the local listener first and set `CROW_LISTEN_PUBLIC_URL` or pass `--public-url`.

## Compatibility Aliases

The old Artisan-style command names are still available as aliases:

```bash
crow crow:plan
crow crow:read
crow crow:listen
```

The preferred CLI interface is:

```bash
crow plan
crow read
crow listen
```

## Development

Install dependencies:

```bash
composer install
```

Run the test suite:

```bash
composer test
```

Inspect available commands:

```bash
php crow list
```

Build the PHAR:

```bash
php crow app:build crow --build-version=unreleased
```

Smoke-test the built artifact:

```bash
./builds/crow plan --help
```

## Release Notes

This repository is now the standalone Crow CLI. It is no longer a Laravel installable package that auto-registers Artisan commands inside a host application.

For Packagist distribution, `composer.json` points its `bin` entry at `builds/crow`, so release builds should include a fresh PHAR artifact.
