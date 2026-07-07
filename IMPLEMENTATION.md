# Laravel Zero CLI Migration Plan

## Summary

Convert this repo in place from a Laravel package into a Laravel Zero 12 standalone CLI named `crow`, with Composer package identity `crowbot/cli`. The primary UX becomes PHAR-first distribution through Packagist, short commands like `crow plan`, and a new `crow auth login` setup flow.

Use the current Laravel Zero docs path: `composer create-project` style skeleton, commands in `app/Commands`, config in `config`, PHAR builds via `php crow app:build crow`, and Packagist PHAR bin behavior from the PHAR distribution docs.

## Key Changes

- Replace package scaffolding with Laravel Zero app scaffolding:
  - `composer.json` becomes a Laravel Zero project using `laravel-zero/framework:^12`.
  - Rename app executable to `crow`.
  - Set Composer package name to `crowbot/cli`.
  - Remove Laravel package auto-discovery and `CrowListenServiceProvider`.
  - Add `box.json`, `bootstrap/app.php`, Laravel Zero `config/app.php`, and `config/commands.php`.
- Move reusable code into app namespace:
  - Existing commands become Laravel Zero commands under `app/Commands`.
  - Existing client, formatter, and listener classes move under `app/Support` or equivalent `App\...` namespace.
  - Keep behavior of current `plan`, `read`, and `listen` flows unless explicitly changed below.
- Command UX:
  - Primary commands are `crow plan`, `crow read`, and `crow listen`.
  - Add hidden/compat aliases for `crow:plan`, `crow:read`, and `crow:listen` where Laravel command aliasing allows it.
  - Update command output that currently says `php artisan crow:plan <plan-id>` to say `crow plan <plan-id>`.
- Auth/config:
  - Add `crow auth login`.
  - Prompt for API token and optional API URL.
  - Store credentials in `~/.crow/config.json` with restrictive permissions where supported.
  - Config precedence: explicit command option, environment variable, `~/.crow/config.json`, then default config.
  - Keep default API URL as the current `https://crow.test/api/v1`.
  - Preserve existing env vars like `CROW_API_TOKEN`, `CROW_API_URL`, `CROW_APP_ID`, and listener options for automation.
- PHAR-first distribution:
  - Build artifact name is `builds/crow`.
  - Composer `bin` points to `builds/crow` for release/Packagist distribution.
  - Move Laravel Zero runtime dependencies to the release model recommended by the docs for PHAR Packagist installs.
  - Include Laravel Zero `dotenv` and `http` components so HTTP and adjacent `.env` files work in PHAR usage.

## Test Plan

- Port current PHPUnit/Testbench command tests to Laravel Zero app tests.
- Cover:
  - `crow plan` lists plans and prints `crow plan <plan-id>`.
  - `crow plan <id>` outputs markdown/json and handles API failures.
  - `crow read` fetches latest or by ID and respects `--leave-unread`.
  - `crow listen` preserves listener registration/no-register behavior.
  - `crow auth login` writes `~/.crow/config.json` and command config reads it.
  - Env vars override stored config.
  - Hidden compatibility aliases still execute the same command behavior.
- Add build verification:
  - `composer test` or equivalent test command passes.
  - `php crow list` shows the expected commands.
  - `php crow app:build crow --build-version=<version>` creates `builds/crow`.
  - `./builds/crow plan --help` runs successfully.

## Assumptions

- This repo stops being a Laravel installable package and becomes only the standalone CLI.
- v1 does not add native single-file binaries; PHAR is the release artifact.
- `crow auth login` is the only new auth command for this migration; logout/status can be added later.
- Production still defaults to `https://crow.test/api/v1` until a different URL is provided.
