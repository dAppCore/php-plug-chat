# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

`lthn/php-plug-chat` is a PHP library providing chat platform integrations (Discord, Slack, Telegram) for the Plug framework. It depends on `lthn/php` which supplies the core contracts and concerns.

## Build & Install

```bash
composer install
```

No tests, linter, or CI are configured yet.

## Architecture

**Namespace:** `Core\Plug\Chat\` (PSR-4 mapped to `src/`)

Each chat provider lives in its own subdirectory and implements contracts from `lthn/php` (`Core\Plug\Contract\*`):

| Contract | Purpose | Discord | Slack | Telegram |
|---|---|---|---|---|
| `Authenticable` | Auth/credential validation | `Auth` | `Auth` | `Auth` |
| `Postable` | Send messages | `Post` | `Post` | `Post` |
| `Deletable` | Delete messages | `Delete` | — | `Delete` |
| `Readable` | Read bot/chat info | — | — | `Read` |
| `Listable` | List chats | — | — | `Chats` |

**Shared concerns** (from `lthn/php`): `BuildsResponse`, `UsesHttp`, `ManagesTokens`. All classes use `BuildsResponse` + `UsesHttp`; Telegram classes that need stored credentials also use `ManagesTokens`.

**Auth model:** Discord and Slack use webhook URLs (no OAuth). Telegram uses Bot API tokens.

## Conventions

- All files use `declare(strict_types=1)`
- Fluent setters for configuration (e.g., `withWebhook()`, `withBotToken()`, `toChatId()`)
- All public API methods return `Core\Plug\Response` via `$this->fromHttp()`, `$this->ok()`, or `$this->error()`
- Response transform callbacks normalize API-specific data into flat associative arrays
- Media is passed as `Illuminate\Support\Collection` items with `url`/`path` keys
