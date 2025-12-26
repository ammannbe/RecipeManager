# Copilot / AI Agent Instructions — RecipeManager

This project is a Laravel 12 application using Livewire (Flux/Volt) and Laravel Fortify for auth. The goal: help contributors (and AI agents) make focused changes quickly — follow the patterns below.

Quick start (most common commands)
- Setup dev environment: run `composer run-script setup` (see [composer.json](composer.json)).
- Local dev: `composer run-script dev` runs a combined stack (serves app, queue listener, logs, vite). You can also run `php artisan serve` + `npm run dev` separately.
- Build frontend: `npm run build` (see [package.json](package.json)).
- Run tests: `composer run-script test` or `php artisan test`. Tests use an in-memory SQLite DB (see [phpunit.xml](phpunit.xml)).

Big-picture architecture
- Framework: Laravel application (backend) with Livewire-driven UI (see `app/Livewire/` and [routes/web.php](routes/web.php)).
- Auth: Laravel Fortify handles authentication flows; UI routes use `Volt::route()` (Livewire Volt components).
- Data: Eloquent models in `app/Models/` with Observers in `app/Observers/` and factories under `database/factories/`.
- Jobs/Queues: Queues are used but tests run with `QUEUE_CONNECTION=sync` (fast local tests).

Project-specific conventions
- Livewire + Volt: UI pages are implemented as Volt components and wired via `Volt::route()` (see [routes/web.php](routes/web.php)). Follow existing component structure under `app/Livewire`.
- Actions & Livewire Actions: Reusable actions live in `app/Actions/` and `app/Livewire/Actions/` — prefer using these for encapsulated business logic.
- Observers: Domain behaviors use observers (`app/Observers`) — follow these patterns for cross-model behavior.

Testing and CI hints
- Tests use sqlite :memory: (see [phpunit.xml](phpunit.xml)) so no DB setup required for CI; ensure migrations/factories are used to seed state in tests.
- Environment overrides in tests: `QUEUE_CONNECTION=sync`, `CACHE_STORE=array`, etc., are set in `phpunit.xml` — do not rely on external services in unit tests.

Developer tooling
- Static analysis: run `composer run-script phpstan` or `./vendor/bin/phpstan`.
- Formatting: `composer run-script pint` runs Laravel Pint.
- IDE helpers: `composer run-script ide-helper` generates IDE metadata; useful after model changes.

Integration points & external dependencies
- Composer repo `flux-pro` is defined in `composer.json` (private flux packages). Be cautious when modifying Livewire/Flux usage.
- Frontend: Vite + Tailwind. Frontend commands are in [package.json](package.json).

What to look for when editing
- Routes & UI: If adding pages, register a `Volt::route()` entry in `routes/web.php` and add the corresponding Livewire Volt component under `app/Livewire` and a view in `resources/views`.
- Model changes: Update observers, factories, and tests. Keep traits and scopes consistent with existing naming.
- Tests: Always run `composer run-script test` locally. Use the in-memory DB and `QUEUE_CONNECTION=sync` assumptions.

Examples (copy/paste)
- Run full dev stack: `composer run-script dev`
- Run tests: `composer run-script test`
- Generate IDE helpers: `composer run-script ide-helper`

If something is missing or unclear
- Ask for the file or area to inspect; point to `app/` subfolders or `routes/web.php` for UI linkage. I will read examples and adapt instructions.

---
Please review these notes — tell me which areas you want expanded (tests, CI, Livewire patterns, or common refactors).
