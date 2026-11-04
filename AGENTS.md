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
* Self-registration (`App\Filament\Auth\Register`) runs in a DB transaction, has a honeypot and a
  3 s time trap; `users:prune-unverified` (daily) deletes unverified users after 7 days.

### Optional OpenID Connect login (SSO)

* Configured in `config/login.php` (`AUTH_LOCAL_*`, `AUTH_OIDC_*`) and `config/services.php` (`oidc`:
  issuer, client id/secret). Off by default; any OIDC provider works (Laravel Socialite +
  `kovah/laravel-socialite-oidc`, JWT signature verified). `App\Auth\OidcProvider` additionally
  checks `iss`, `aud`/`azp` and `exp` (`OidcClaims`).
* Routes `/auth/oidc/redirect` and `/auth/oidc/callback` (`OidcController`), matching in
  `App\Auth\OidcLogin`: by `users.oidc_sub`, otherwise by e-mail only if the provider marks it as
  verified. Linking an unverified local account verifies it, removes its password and ends its sessions
  (someone else may have registered the address; `AuthenticateSession` skips users without password). Unknown users get an account (`AUTH_OIDC_REGISTRATION`) with a
  unique author name and no password. `AUTH_OIDC_REQUIRED_GROUP` checks the `groups` claim.
* `AUTH_LOCAL_ENABLED=false` hides the password form (`/admin/login?local=1` shows it) and allows
  password logins for admins only (emergency access). Password reset stays available, so SSO-only
  users can set a password (rollback path). `AUTH_LOCAL_REGISTRATION=false` removes registration.
* Profile: "Link …" / "Unlink …" (unlink only with a password, otherwise the account is lost).
* Button label `App\Auth\OidcLabel::get()`: `AUTH_OIDC_LABEL`, otherwise the `appName` (Pocket ID's
  `APP_NAME`) from the public `<issuer>/api/application-configuration` (cached a day, a failure an hour),
  otherwise "SSO". `filament/oidc-login-button.blade.php` sits above the login and registration form
  (render hooks `AUTH_LOGIN_FORM_BEFORE`/`AUTH_REGISTER_FORM_BEFORE`; registration only with
  `AUTH_OIDC_REGISTRATION`); `AUTH_OIDC_SIGNUP_URL` (e.g. the AccountManager's `/register`) adds
  "No … account yet?". Users switch to SSO voluntarily: linking keeps the local account and its data.
* Local Pocket ID: `.ddev/docker-compose.pocket-id.yaml` (`https://recipemanager.ddev.site:14111`,
  first start `/setup`; dev-only keys; Application Configuration via env (`UI_CONFIG_DISABLED=true`),
  mails to Mailpit). The apps use `AUTH_OIDC_ISSUER=http://ddev-RecipeManager-pocket-id:1411` (also Shoppinglist and
  Chorelist, which reach it through the shared `ddev_default` network).
  Set the `AUTH_OIDC_*` values in `.ddev/config.local.yaml` (`web_environment`), not in git.
  Tests mock Socialite (`tests/Feature/OidcLoginTest.php`).

## Companion App API (Shoppinglist)

JSON API for the companion app "Shoppinglist", secured with Laravel Passport
(OAuth 2 authorization code grant, confidential client).

* Routes: `routes/api.php`, guard `auth:api`, `throttle:120,1`. Code in
  `app/Http/Controllers/Api`, `app/Http/Requests/Api`, `app/Services/Api`.
* `GET /api/user`, `GET /api/foods` (with `season_months` / `storage_months`, empty arrays
  without data), `GET /api/vocabulary` (category and unit names, complexities),
  `POST /api/recipes/search` (optional `season_month` 1–12: after `matched_foods_count`,
  recipes with more in-season foods rank first), `GET /api/recipes/{id}?servings=`,
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
* Trusted clients (`PASSPORT_TRUSTED_CLIENTS`, comma separated client IDs, `App\Models\OAuthClient`):
  own companion apps skip the approval screen for verified users; with the OIDC login enabled,
  guests of these clients go straight to `/auth/oidc/redirect` instead of the login form
  (`redirectGuestsTo` in `bootstrap/app.php`), so connecting needs no input when the user is
  already logged in at the provider.
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

## AI features (OpenAI)

* `config/services.php` `openai` (`OPENAI_API_KEY`, `OPENAI_MODEL`, `OPENAI_REASONING_EFFORT`,
  `OPENAI_IMAGE_MODEL`, `OPENAI_IMAGE_QUALITY`, `OPENAI_HOURLY_LIMIT` default 20). Without a key the
  AI features are hidden. One global key; `App\Ai\OpenAiClient` (Responses API with strict JSON schema
  + Images API via `Http`, no SDK, same as Shoppinglist) logs the token usage per request.
* Access: `App\Ai\RecipeAssistant::allows()`: verified users with `users.ai_enabled` (default on,
  admins switch it off in the Author resource's user section). Every request (recipe or photo) counts
  against `ai:<user id>` per hour.
* Import wizard step 1 "Create with AI": photo/PDF (max. 10 MB) and/or text → `RecipeAssistant::recipe()`
  (the manual prompt of `ImportPromptBuilder` + `RecipeJsonSchema::aiSchema()`) fills the JSON step; resolve
  and confirm stay the same. "Manually via chat" stays as collapsed section. Toggle "Also create a photo"
  calls `RecipeAssistant::image()` after the import (a failure only warns). The recipe edit page has the
  header action "Create photo with AI". Generated photos are stored like uploads, `is_ai_generated` true.
* Tests: `phpunit.xml` forces an empty `OPENAI_API_KEY`; tests set a fake key and `Http::fake()`
  (`tests/Feature/AiRecipeTest.php`).

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

## Seasons

* `foods.season_months` (fresh Swiss harvest, open field or unheated greenhouse) and
  `foods.storage_months` (Swiss storage only, not "in season"): JSON lists of months 1–12, null
  without data. `Food` normalizes them to sorted integers (needed for `JSON_CONTAINS`).
* Data: `database/data/food-seasons.php` (name => [fresh, storage]). `ddev artisan foods:seasons
  {--dry-run} {--force}` assigns it to foods with exactly matching names (case-insensitive,
  whitespace-normalized); months already set are kept without `--force`. It lists data names
  without a food and foods that only contain a data name ("Rüebli, gerieben") for manual
  assignment in the Food resource. A data migration ran it once.
* "In season" means fresh months only, current month in `Europe/Zurich` (`App\Services\Season`).
  `Recipe::scopeInSeason()`: at least one food in season and at least half of the foods with
  season data. Used by the index chip "Seasonal" (`seasonal=1`, combined with the other
  filters). `/season?month=` is the season calendar (year table of all foods with season data,
  foods of the chosen month first, its fresh cells dark green; linked from Shoppinglist's week
  planner); the recipe page names the foods in season ("In season: …").

## Public Frontend

* Blade + Tailwind v4 + Alpine.js (no Alpine plugins), dark mode via `dark:`, zinc/violet palette (violet = the logo color).
  Alpine components live in `resources/js/app.js`; run `ddev npm run build` after changes.
* Icon: the Narrenhaus jester in violet in a cooking pot, master `src/logo.svg`, rendered to
  `src/favicon.png` (1024 px, transparent). Favicons (`partials/favicon.blade.php`, also in the
  panel via `HEAD_END`): `ddev npm run favicon -- --source src/favicon.png --theme '#7c3aed' --bg '#fafafa' --padding-bg '#ede7fd' --clean`.
  The nav, the panel brand (`filament/brand.blade.php`) and the OAuth page show `favicon-96x96.png`.
  Link previews: `partials/meta.blade.php` + `public/og-image.png` (1200×630).
* Navigation `partials/nav.blade.php` (`siteNav`): desktop (≥ md) text links Recipes / Season
  calendar (active pill, `aria-current`), then language, theme and Login/Backend (the only button).
  Phones: hamburger opening a slide-down panel (backdrop, Escape/link closes, focus moved in and
  trapped, page scroll locked) with the links, language, dark mode switch and Login/Backend.
* Recipe filters (`recipes/index.blade.php`, `recipeFilters`): one form `#recipe-filters`, the search
  field always visible. Desktop: inline panel (collapsed unless a non-search filter is active),
  changes submit instantly. Phones: the same panel becomes a bottom sheet opened by the floating
  "Filters" button, applied only with "Show recipes"; closing restores the fields of the current URL.
  Option pills show their state with `has-checked:`. Empty fields and the default sort are dropped
  from the submitted URL. Active filters are chips built in `RecipeController::index()`
  (`activeFilters`: key, label, URL without that filter). Query names: `search`, `quick`,
  `seasonal`, `complexity`, `category`, `cookbook`, `sort`, `tags[]`.

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
