# AI Development Instructions

## Project

This is a Laravel recipe management application.

The app manages:
- cookbooks, shared with other users via e-mail invitations, public or private
- recipes, public by default and publishable individually or per cookbook; a recipe
  defaults to private when created in a private cookbook
- foods
- tags

## Tech Stack

- Laravel v13
- DDEV
- MariaDB
- Filament v5
- Laravel Livewire v4
- PHPUnit
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
* Use PHPUnit test classes in `tests/Feature`.
* Use factories for test data.
* Use Filament for main CRUD interfaces.
* Only authenticated users can access the app backend.
* Guests may only see recipes that are published.
* Recipe photos live on the private `recipes` disk and are served through an authorized route. Never move them back under `public/`.

## Authentication Rules

* Use Filament authentication.
* Users should be able to sign-up themselves.

## Testing Rules

Add or update tests for every functional change.

Important test areas:

* authentication
* policies
* cookbook sharing and invitations
* recipe visibility, including photo access
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
