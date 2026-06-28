# Crow CLI

This repository contains `crowbot/listen`, a Laravel package that lets a local
developer or agent read Crow handoff events from the command line.

It provides two Artisan commands:

- `crow:read`: fetch one unread Crow event and print an AI-agent-ready brief.
- `crow:listen`: run a local HTTP listener for live Crow events forwarded from
  the Crow API.

The package is required by `crow-api` as `crowbot/listen`.

## Requirements

- PHP 8.2+
- Composer
- A Crow API token with access to listener events
- A running Crow API, usually `https://crow.test/api/v1`
- expose.dev or another tunnel if using live push delivery with `crow:listen`

## Install In A Laravel App

Require the package through Composer. In the local Crow workspace, the API uses
the package as a path/dev dependency.

Publish config when needed:

```bash
php artisan vendor:publish --tag=crow-listen-config
```

Configure environment variables:

```dotenv
CROW_API_URL=https://crow.test/api/v1
CROW_API_TOKEN=your-sanctum-token
CROW_APP_ID=
CROW_LISTEN_PUBLIC_URL=
CROW_LISTEN_SECRET=
CROW_LISTEN_HOST=127.0.0.1
CROW_LISTEN_PORT=8787
```

Required:

- `CROW_API_URL`: Crow API base. The package appends `/api/v1` if missing.
- `CROW_API_TOKEN`: bearer token used for API requests.

Optional:

- `CROW_APP_ID`: limit reads/listeners to a Crow app.
- `CROW_LISTEN_PUBLIC_URL`: public tunnel URL Crow can call for live events.
- `CROW_LISTEN_SECRET`: fixed signing secret for listener webhooks. If omitted,
  `crow:listen` generates a temporary secret.
- `CROW_LISTEN_HOST` and `CROW_LISTEN_PORT`: local listener bind address.

## Read Events

Fetch the latest unread event:

```bash
php artisan crow:read
```

Fetch a specific event:

```bash
php artisan crow:read EVENT_ID
```

Filter by app or event type:

```bash
php artisan crow:read --app-id=1 --events=recon.ready
```

Print raw JSON and leave the event unread:

```bash
php artisan crow:read --json --leave-unread
```

## Listen For Live Events

Start a local listener and register it with Crow:

```bash
expose share --subdomain=your-name --server=us-2 http://127.0.0.1:8787
CROW_LISTEN_PUBLIC_URL=https://your-name.us-2.sharedwithexpose.com php artisan crow:listen
```

Run without registering, useful for local webhook tests:

```bash
php artisan crow:listen --no-register --port=8787
```

Health endpoint:

```text
GET /health
```

Event endpoint:

```text
POST /crow/events
```

Live events are signed with:

```text
X-Crow-Timestamp
X-Crow-Signature
```

The signature is `hash_hmac('sha256', timestamp + "." + body, secret)`.

## Local Package Development

From this package directory:

```bash
composer install
vendor/bin/phpunit
```

Useful files:

- `src/Commands/CrowReadCommand.php`: one-shot event fetch command.
- `src/Commands/CrowListenCommand.php`: live listener command.
- `src/CrowApiClient.php`: Crow API wrapper.
- `src/ListenerServer.php`: small local HTTP listener.
- `src/EventFormatter.php`: markdown/JSON output formatting.
- `config/crow-listen.php`: environment-backed config.

When changing package behavior through the `api/` app, run the API's relevant
tests as well as this package's PHPUnit suite.
