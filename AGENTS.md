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
- Laravel Passport v13 (OAuth for the companion app API)
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

## Companion App API (Shoppinglist)

JSON API for the companion app "Shoppinglist", secured with Laravel Passport
(OAuth 2 authorization code grant, confidential client).

* Routes: `routes/api.php`, guard `auth:api`, `throttle:120,1`. Code in
  `app/Http/Controllers/Api`, `app/Http/Requests/Api`, `app/Services/Api`.
* `GET /api/user`, `GET /api/foods`, `GET /api/vocabulary` (category and unit names,
  complexities), `POST /api/recipes/search`, `GET /api/recipes/{id}?servings=`,
  `POST /api/recipe-drafts`.
* Visibility always goes through `Recipe::visibleTo()` / `RecipePolicy` (invisible → 404).
  Photos are never exposed.
* Ingredient text and scaling mirror the recipe page (`recipes/show.blade.php`, `recipeServings`
  in `app.js`); alternatives (`ingredient_id`) are returned as `alternatives` of their parent.
* The API never creates recipes, foods, units etc. directly. `POST /api/recipe-drafts` takes a
  recipe in the import JSON format (`RecipeJsonValidator`, no photos), stores it as `RecipeDraft`
  (own user, expires after 24 h, pruned daily by `recipe-drafts:prune`) and returns the URL
  `/admin/import-recipe?draft=<uuid>`. The import wizard loads the draft and starts at "Resolve",
  where the user picks cookbook and visibility and matches unknown values; the draft is deleted
  after the import.
* OAuth: `GET /oauth/authorize` (guests log in via Filament first, unverified users are sent to
  e-mail verification), `POST /oauth/token` (`authorization_code`, `refresh_token`).
  Scope `recipes` is optional. Access tokens live 1 hour, refresh tokens 90 days. The approval
  screen (`resources/views/passport/authorize.blade.php`) is skipped while the user still has a
  valid access token for the client.
* Create the client once per Shoppinglist installation and give its ID and secret to Shoppinglist
  (the secret is shown only once):

```bash
ddev artisan passport:client --name=Shoppinglist --redirect_uri=https://<shoppinglist-host>/recipes/callback -n
# production: php artisan passport:client --name=Shoppinglist --redirect_uri=https://<shoppinglist-host>/recipes/callback -n
```

* Deployment: run `php artisan migrate --force` (oauth_* tables), then create the keys once with
  `php artisan passport:keys` (stored in `storage/oauth-*.key`, git-ignored, must persist across
  deployments) or set `PASSPORT_PRIVATE_KEY` / `PASSPORT_PUBLIC_KEY` in the environment.
  Never commit the keys. Rotating the keys invalidates all issued tokens.

## Lookup Requests and Import Decisions

* Foods, units, ingredient attributes and tags use `Requestable` (`requested_by`, `requested_at`):
  non-admins create them in the import wizard as requests (`LookupType::createsRequest()`), usable
  in their recipe right away. Option lists, the import prompt, the API and the admin resources use
  `visibleTo($user)` (approved + own requests, admins all); recipes using a request show it to
  everyone. Requesting a name someone else requested reuses that entry (unique name index).
* Admins approve requests or merge them into an existing entry (`LookupRequests` table actions,
  `LookupMerger`: repoints ingredients and pivot rows, then deletes the source). The resources show
  the number of open requests as navigation badge. Every admin gets a mail (`LookupRequestedMail`,
  in their locale) when an import created requests.
* "Omit" in the resolve step: units, attributes and tags are dropped; an omitted food drops its
  ingredient (with alternatives) and a group left empty. Units and attributes only used on omitted
  ingredients need no decision (`isObsolete()` in the wizard, `isUsed()` in `RecipeImporter`).
  Categories, cookbooks and authors cannot be omitted; at least one ingredient must remain.

## Testing Rules

Add or update tests for every functional change.

Important test areas:

* authentication
* policies
* cookbook sharing and invitations
* recipe visibility, including photo access
* API (OAuth, visibility, search ranking, scaling, recipe creation)
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
