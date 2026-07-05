# AI Development Instructions

## Project

This is a Laravel recipe management application.

The app manages:
- cookbooks
- recipes
- recipe ratings
- foods
- tags

## Tech Stack

- Laravel v13
- DDEV
- MariaDB
- Filament v5
- Laravel Livewire v4
- Pest
- Laravel Pint
- Larastan

## Command Rules

Use DDEV commands only:

```bash
ddev artisan
ddev composer
ddev npm
ddev <custom command>
````

After code changes, run:

```bash
ddev composer test
ddev composer pint
ddev composer phpstan
```

If any command fails, repeat the process from the beginning.

## General Rules

* Use Laravel conventions unless explicitly told otherwise.
* Prefer simple code over abstraction.
* Do not introduce new packages without asking first.
* Do not edit vendor files.
* Use Form Requests for validation in custom controllers.
* Use Policies for authorization.
* Use Pest for tests.
* Use factories for test data.
* Use Filament for main CRUD interfaces.
* Only authenticated users can access the app.
* Guests must not access the app backend.

## Authentication Rules

* Use Filament authentication.
* Users should be able to sign-up themselves.

## Testing Rules

Add or update Pest tests for every functional change.

Important test areas:

* authentication
* policies
* filament pages
* filament resources
* profile update

## Before Finishing

Report:

* what changed
* tests result
* pint result
* phpstan result
* open follow-ups
