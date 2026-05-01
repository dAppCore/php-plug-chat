# AGENTS.md — php-plug-chat

CorePHP package shape:

- `src/` — PSR-4 `Core\Plug\Chat\\\`
- `tests/` — Pest test suite
- `composer.json` — `    "name": "lthn/php-plug-chat",`, EUPL-1.2, PHP 8.4+

## CI

- `.github/workflows/ci.yml` — Pest + Pint + PHPStan
- `.woodpecker.yml` — mirrored for forge.lthn.sh

## Coding standards

- `declare(strict_types=1);` in every PHP file
- Type hints on every parameter + return type
- Pest test syntax
- PSR-12 via Laravel Pint
