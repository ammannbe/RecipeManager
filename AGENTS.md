# AI Development Instructions

## Project

This is a Laravel recipe management application.

The app manages:
- cookbooks, shared with other users via e-mail invitations or with households, public or private
- households (groups of users that cookbooks are shared with)
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
* Routes `/auth/oidc/redirect` and `/auth/oidc/callback` (`OidcController`, intents `login`, `link`, `confirm`), matching in
  `App\Auth\OidcLogin`: by `users.oidc_sub`, otherwise by e-mail only if the provider marks it as
  verified (an unverified local account gets verified). Unknown users get an account (`AUTH_OIDC_REGISTRATION`) with a
  unique author name and no password. `AUTH_OIDC_REQUIRED_GROUP` checks the `groups` claim.
* `AUTH_LOCAL_ENABLED=false` hides the password form (`/admin/login?local=1` shows it) and allows
  password logins for admins only (emergency access). Password reset stays available for unlinked accounts.
  `AUTH_LOCAL_REGISTRATION=false` removes registration.

### Linked accounts are SSO-only (reference for Shoppinglist and Chorelist)

* A linked account (`oidc_sub` set, `User::isLinkedToOidc()`) signs in through the provider only. All rules live in
  `App\Auth\OidcAccount`; `UserObserver::saving` throws if a linked account would get a password (link/unlink only
  through `OidcAccount`, the factory drops the password of `linked()` users).
* Linking (first OIDC sign-in matched by e-mail, or "Link …" in the profile) = `OidcAccount::link()`: password null,
  remember token rotated, password reset tokens deleted, the user's other sessions deleted (database session driver
  only; other drivers: they run out after `SESSION_LIFETIME`, `AuthenticateSession` skips users without password),
  then the callback signs in again with a new remember cookie. OAuth tokens/MCP grants stay (approved clients,
  revocable under "Connected AI clients"; unverified users cannot get any). The app has no passkeys/MFA/recovery codes;
  add them to `link()` if that changes.
* "Link …" in the profile first asks for the current password (accounts without one skip it): the action stores
  `oidc.password_confirmed_at`, `/auth/oidc/redirect?link=1` needs it (5 min, used once), otherwise back to the profile.
* Local login: `Login::isUserAllowedToAccessPanel()` refuses linked accounts (also with a leftover password) and
  `throwFailureValidationException()` answers "This account is connected to :provider. Sign in with :provider."
  (`OidcAccount::message()`, :provider = `OidcLabel`). Enumeration: this only adds "linked or not" for addresses
  whose account exists, which registration ("already taken") and the reset page ("can't find a user") tell anyway;
  unknown addresses and wrong passwords keep Filament's message, the login stays rate limited.
* Password reset (`App\Filament\Auth\RequestPasswordReset`/`ResetPassword`): a linked account gets no token but the
  mail `SignInWithOidcNotice` (once per 15 min) and the page shows the same "sent" message as for any account; a
  reset link of a linked account (e.g. older than the link) is refused with the message above and its token deleted.
* Unlinking ("Unlink …", only with the OIDC login enabled) = `OidcAccount::unlink()`: needs an OIDC sign-in within
  15 min (`oidc.signed_in_at`, set by every sign-in/link/confirmation). Otherwise the action sends the user to
  `/auth/oidc/redirect?confirm=1`; the callback checks that the provider returns the same `sub` and reopens the
  form (`?action=unlinkOidc`). The form takes a new password + confirmation (`Password::default()`); unlinking sets
  it, rotates the remember token, ends the other sessions and moves mirrored households to pending
  (AccountManager driver). The provider may confirm without a prompt while the user is still signed in there.
* Profile (`App\Filament\Auth\EditProfile`, same structure as "Manage account" in the settings of Shoppinglist and
  Chorelist): section "Author" (the author name: a field of the recipe collection, shown on recipes and cookbooks,
  unique, stays editable for everybody, never taken from the provider) and section "Manage account". Local accounts:
  e-mail and password there. Linked accounts show "Connected with …" instead of the password fields, the e-mail is
  read-only (changing it needs the current password); with `ACCOUNT_MANAGER_URL` the header action "Manage account
  in “:name”" opens `AccountManagerLabel::profileUrl()` in a new tab (the portal's public address from `/api/info`
  `households_url` if known, otherwise `ACCOUNT_MANAGER_URL` + `/profile`). Header actions "Link :provider" /
  "Unlink from :provider"; signing out is in the user menu. No self-deletion (deleting users: admins, `user.deleted`).
  Admins: Authors list column "Sign-in", the author form hides the password of linked accounts.
* Existing data: migration `2026_11_13_090000_remove_local_login_from_linked_accounts` (passwords, remember tokens,
  reset tokens and sessions of linked accounts; linked users sign in again once). Unlinked accounts are untouched;
  accounts without password and without `oidc_sub` can still use "Forgot password".
* Emergency admins must stay unlinked (with `AUTH_LOCAL_ENABLED=false` they are the only password login).
  Way back (provider gone, lost provider account): `users:unlink-oidc {emails*} {--all}` unlinks without password,
  the users then use "Forgot password".
* Tests: `OidcLinkedAccountTest` (shared Socialite mock `Tests\Feature\Concerns\MocksOidcProvider`).

### OIDC login button and local test

* Button label `App\Auth\OidcLabel::get()`: `AUTH_OIDC_LABEL`, otherwise the `appName` (Pocket ID's
  `APP_NAME`) from the public `<issuer>/api/application-configuration` (cached a day, a failure an hour),
  otherwise "SSO". `filament/oidc-login-button.blade.php` sits above the login and registration form
  (render hooks `AUTH_LOGIN_FORM_BEFORE`/`AUTH_REGISTER_FORM_BEFORE`; registration only with
  `AUTH_OIDC_REGISTRATION`); `AUTH_OIDC_SIGNUP_URL` (e.g. the AccountManager's `/register`) adds
  "No … account yet?". Users switch to SSO voluntarily: linking keeps the local account and its data (not its password).
* Local test: Pocket ID runs in the AccountManager DDEV project (`https://accountmanager.ddev.site:14111`,
  see its `AGENTS.md`), reached at `AUTH_OIDC_ISSUER=http://ddev-AccountManager-pocket-id:1411` through
  the shared `ddev_default` network.
  Set the `AUTH_OIDC_*` values in `.ddev/config.local.yaml` (`web_environment`), not in git.
  Tests mock Socialite (`tests/Feature/OidcLoginTest.php`).

## Households

* Concept: `~/Projekte/AccountManager/docs/KONZEPT.md` 5.6 (phase 7 "local"; phase 8 mirrors the AccountManager).
  Same design and names in Shoppinglist and Chorelist.
* `config/households.php` `driver` (`HOUSEHOLDS_DRIVER`): `local` (default) manages households here; `account-manager` mirrors
  them from the AccountManager (see below) and makes the local management read-only (`Households::isLocal()`:
  `HouseholdPolicy` forbids create/rename/delete/invite/roles/remove/leave, invitation links and pending invitations
  are refused); sharing cookbooks with households stays possible.
* Tables `households` (uuid, name, `created_by`, `account_manager_household_uuid`, `account_manager_version`, `account_manager_import_status`,
  `account_manager_shares_hash`), `household_user` (role `admin`|`member`, `joined_at`),
  `household_invitations` (like `cookbook_invitations`: token hash, role, `invited_by`, 14 days,
  `household-invitations:prune` daily) and `cookbook_household` (same grants as `cookbook_user`).
* `App\Services\Households` keeps the rules: the creator is admin; at least one admin stays (`LastHouseholdAdmin`
  on removing, demoting or leaving the last one, admin rows locked); deleting a household or removing a member only
  removes access, contents stay with their owner; a removed or leaving member's own cookbooks are no longer shared
  with the household (`Household::detachMembers()`, same in Shoppinglist/Chorelist); deleting a user (`UserObserver`) drops the memberships, hands an
  admin-less household to its longest member and deletes empty ones.
* Invitations always need consent, also for existing users (unlike cookbooks): `/household-invitations/{token}`
  (`HouseholdInvitationController`) accepts only for a logged-in user with the invited, verified address; guests go
  to login (intended URL) and the token waits in the session, verifying the address accepts it
  (`AcceptHouseholdInvitationFromSession`). Pending invitations to one's address are accepted or declined on the
  households page (`PendingHouseholdInvitations` header widget). The invite notice never tells whether an account
  exists.
* UI: `HouseholdResource` (navigation and user menu "Households", list + view page; only own households, also for
  global admins), relation managers Members (invite, change role, remove), Pending invitations (admins) and Shared
  cookbooks (read-only). Cookbooks: `HouseholdsRelationManager` "Shared with households" (users with `share` on the
  cookbook share with households they belong to, edit the grants, unshare).
* Access: every check goes through `Cookbook::grants()` / `sharesFor()` (personal membership + shares of the user's
  households, grants add up) and the query scope `Cookbook::scopeSharedWith($user, $grants)`, used by
  `Cookbook::visibleTo()`/`administeredBy()`, `Recipe::visibleTo()`, the Recipe/Cookbook resources and
  `MyCookbooksWidget`. Never query `cookbook_user` directly for access. Tests: `HouseholdTest`, `HouseholdSharingTest`.

### Households from the AccountManager (`HOUSEHOLDS_DRIVER=account-manager`)

* Contract: AccountManager `docs/HOUSEHOLDS-API.md` (phase 8). Env `ACCOUNT_MANAGER_URL`, `ACCOUNT_MANAGER_SERVICE_KEY`, `ACCOUNT_MANAGER_SECRET` (one
  secret signs the webhooks and authenticates the API with HTTP Basic), `ACCOUNT_MANAGER_REPORT_SHARES`. Mirroring is active only
  with the driver and all three values (`AccountManagerHouseholds::enabled()`), otherwise the webhook returns 404. Needs the
  OIDC login (members are matched by `users.oidc_sub`) and the queue worker. Same names as Chorelist and Shoppinglist.
* `App\Households\AccountManagerClient` (`GET /api/households?sub=`, `GET /api/households/{uuid}`, `PUT …/shares`,
  `POST/GET /api/households/import`; connect 3 s, total 10 s, no redirects; every failure is `AccountManagerUnavailable`, 404 is an
  answer), `AccountManagerHouseholds` (applying snapshots), jobs `ApplyAccountManagerEvent`, `ReportAccountManagerShares`.
* Webhook `POST /api/account-manager/webhook` (`routes/api.php`, no session/CSRF, `throttle:120,1`, `Api\AccountManagerWebhookController`):
  HMAC-SHA256 over `"{timestamp}.{body}"` (raw body, `X-Account-Manager-Signature`, `X-Account-Manager-Timestamp` max. 300 s off), accepted
  once per `event_id` (`account_manager_webhook_events`, `model:prune` after 7 days), applied by the queued `ApplyAccountManagerEvent`
  (retried while the AccountManager is down). Always 204 for duplicates, stale and unknown types.
* Every event and API answer is a full snapshot: name, members by `oidc_sub` (unknown subjects →
  `household_pending_members`, attached on the first OIDC sign-in or link), roles, all other members removed.
  Webhooks apply only `version > account_manager_version`, API answers always apply (current truth, also with a lower version, e.g. after a backup restore of the AccountManager). `household.deleted` (or 404) deletes the
  household: memberships, pending members and `cookbook_household` go with it (FK cascades), cookbooks and recipes stay
  with their owners. Events of unknown households are confirmed with `GET /api/households/{uuid}` (404: nothing is
  created). Cookbook access needs no extra step: it is resolved through the membership (`Cookbook::grants()`), so
  added/removed members gain/lose it right away. Removed members' own cookbooks are no longer shared with the household
  (`Household::detachMembers()`, like leaving a local household or unlinking the sign-in).
* `households:sync` (daily 04:15): `GET /api/households?sub=` for every user with `oidc_sub`, mirrored households nobody
  listed are checked one by one (404 deletes), then the shares are reported. The OIDC callback (sign-in and linking,
  `AccountManagerHouseholds::syncOnSignIn()`, total timeout 5 s) attaches pending memberships and syncs the user; unlinking in the
  profile moves the user's mirrored memberships back to pending. AccountManager down: the last state stays, a warning
  is logged, the sign-in works.
* Shares (optional, `ACCOUNT_MANAGER_REPORT_SHARES`): `ReportAccountManagerShares` sends the cookbooks shared with a mirrored household
  (`cookbook`, id, name; trashed ones left out) after sharing/unsharing (`CookbookHouseholdShare` created/deleted events),
  renaming, trashing or restoring a cookbook and in the daily sync; only changes (`account_manager_shares_hash`), failures ignored.
* UI (account-manager): the households list has "Manage households in “:name”" (`AccountManagerHouseholds::portalUrl()`: the
  `households_url` of the AccountManager's `/api/info`, otherwise `ACCOUNT_MANAGER_URL/households`), the columns "Not
  using the app yet" (pending members) and the AccountManager's name (`Household::accountManagerStatus()`: managed in “:name” / proposed /
  not taken over / only in this app); the view page shows the same and "Manage in “:name”". No create, rename,
  delete, invite, roles or leave.
* Name in these texts: `App\Households\AccountManagerLabel::get()`: `ACCOUNT_MANAGER_LABEL`, otherwise the `name` (its `APP_NAME`) from the
  public `ACCOUNT_MANAGER_URL/api/info` (only asked with the account-manager driver; cached a day, a failure 10 minutes), otherwise
  "Account" («Konto»). Tests: `AccountManagerLabelTest`.
* Switching from `local`: set the driver and the AccountManager values, run `households:import`: proposes the local households
  without `account_manager_household_uuid` (members with `oidc_sub` only; households without any stay local, the command warns
  about members without a linked sign-in). An admin of the AccountManager confirms; the hourly
  `households:import --check` maps confirmed ones (`account_manager_household_uuid`, name and members from the snapshot, cookbook
  shares stay; if the household is mirrored already the shares move there, grants add up, and the local one is dropped)
  and marks rejected ones (`account_manager_import_status`, they stay local and read-only). Members without a linked sign-in lose
  the household access on confirmation.
* Back to `local` (Rückbau): mirrored households become ordinary local households (management enabled again,
  `account_manager_household_uuid`/`account_manager_version` stay, pending members are ignored, households without `created_by` are fine),
  shares stay; the webhook answers 404. Switching to `account-manager` again overwrites them with the next newer snapshot.
* Deleting a user: in mirrored households the user only leaves (no hand-over, the AccountManager decides).
* Tests: `AccountManagerHouseholdTest` (fake AccountManager via `Http::fake`, signed webhooks, Socialite mock).

## Entitlements from the AccountManager

* Contract: AccountManager `docs/ENTITLEMENTS-API.md` (phase 6a), concept 5.3/6. Same design and names in Shoppinglist and
  Chorelist. `config/entitlements.php`: `driver` (`ENTITLEMENTS_DRIVER`, `local` default) and `admin_source`
  (`AUTH_ADMIN_SOURCE`, `local` default); uses the `ACCOUNT_MANAGER_*` values of `config/households.php`, the same client
  (`AccountManagerClient::entitlements()`, `GET /api/entitlements/{sub}`, 404 = no access) and the same webhook.
* `App\Entitlements\Entitlements` (static): `has($user, 'key')`, `features()`, `isAdmin()`, `status()`, `allowsAccess()`.
  Local driver: every user has access and `entitlements.local.features` (`ENTITLEMENTS_LOCAL_FEATURES`, comma separated,
  empty here; the AI features stay per user, `users.ai_enabled`). Account-manager driver: linked users (`oidc_sub`) get the stored snapshot (features only while
  `active`); never synced = access but no features; users without a linked sign-in keep the local behaviour.
* Admin rights: always `$user->isAdmin()` / `User::query()->admins()`, never `users.admin` directly (policies, resources,
  widgets, import, mails). `AUTH_ADMIN_SOURCE=account-manager`: linked users are admins only with `admin: true` in an
  active snapshot (the local flag of linked users is ignored and read-only in the author form), unlinked admins
  (emergency admins) stay admins.
* Storage: `user_entitlements` (user_id unique, status, admin, features JSON, version, synced_at; model `UserEntitlement`,
  `User::entitlement()`), dropped when `oidc_sub` changes (`UserObserver::updated`, versions count per subject).
  `App\Entitlements\AccountManagerEntitlements`: webhooks apply only `version > stored`, API answers always apply (current truth, also with a lower version, e.g. after a backup restore of the AccountManager); 404 sets
  `none` and keeps the version. `entitlements.changed` for unknown subjects is ignored (204): the OIDC callback fetches the
  snapshot (`syncOnSignIn()`, total timeout 5 s, failures keep the last state and only log) and `entitlements:sync`
  (daily 04:30) fetches it for every linked user (a failure stops the run, the state stays). Snapshots are stored when
  the driver or the admin source is `account-manager` and the AccountManager is configured (`Entitlements::syncEnabled()`).
* Webhook `POST /api/account-manager/webhook` (`AccountManagerWebhookController`): open when households are mirrored or
  entitlements are synced, otherwise 404. Same signature check and `event_id` idempotency; `entitlements.changed` is applied
  right away in one transaction with the event row (`service` must be ours, otherwise 422; unknown statuses count as
  `none`), `user.deleted` by the queued `DeleteAccountManagerUser`, household events as before, unknown types 204.
* Access (driver account-manager): `EnsureEntitlementAccess` (web group, panel middleware, API and MCP routes) signs out a
  linked user whose status is `suspended`, `requested` or `none` and redirects to the login with `?access=suspended|disabled`
  (`Login::mount()` shows «Dein Zugang zu :app ist gesperrt.» / «… ist nicht freigeschaltet.»); API/MCP get 403 JSON with the
  message. The OIDC callback refuses blocked users after the sync. Passport tokens stay (access returns with the status).
* `user.deleted` = `App\Services\UserDeletion` (also for soft-deleted users): the user's cookbooks with all their recipes
  (like deleting a user always did, also shared ones) and their recipes without cookbook are force deleted (photos go with
  `RecipeObserver`), recipes they added to other users' cookbooks move to that cookbook's author, memberships go
  (`UserObserver`, `Households::forget()`), OAuth tokens/codes/clients, MCP grants, sessions (database driver), drafts
  and import photos; then the user and the author are force deleted. AI usage rows stay without user. Applied whenever the
  webhook is open (also with only mirrored households). Removing the access is no deletion (`status: none`).
* Tests: `AccountManagerEntitlementsTest` (fake AccountManager via `Http::fake`, signed webhooks, Socialite mock).

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
  Each ingredient has `converted` (`{amount, amount_max, unit}` in g/kg or ml/dl/l, scaled, see
  "Unit conversion"), null without amount or conversion data.
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
  `OPENAI_IMAGE_MODEL`, `OPENAI_IMAGE_QUALITY`, `OPENAI_IMAGE_INPUT_FIDELITY` (edits, empty = not sent),
  `OPENAI_IMAGE_SOURCE`, `OPENAI_HOURLY_LIMIT` default 20). Without a key the
  AI features are hidden. One global key; `App\Ai\OpenAiClient` (Responses API with strict JSON schema
  + Images API via `Http`, no SDK, same as Shoppinglist) logs the token usage per request.
* Access: `App\Ai\RecipeAssistant::allows()`: verified users with `users.ai_enabled` (default on,
  admins switch it off in the Author resource's user section). Every request (recipe or photo) counts
  against `ai:<user id>` per hour.
* Import wizard (`ImportRecipe`): 1. "Read recipe": photo/PDF (max. 10 MB) and/or text or web address,
  collapsible "Paste recipe JSON" (exports; the only input without AI rights), cookbook
  and visibility. "Next" lets `RecipeAssistant::recipe()` (prompt of `ImportPromptBuilder` +
  `RecipeJsonSchema::aiSchema()`) write the JSON, only when the inputs changed (`aiInput` hash), and parses it;
  errors appear at the text field. A web address is read by `App\Ai\RecipePage` (schema.org Recipe JSON-LD +
  visible text, public hosts only, redirects checked again against private/reserved IPs, max. 3 MB).
  2. "Resolve" (also optional photos, offered while the JSON has fewer than `MAX_PHOTOS`: an upload and/or "Generate
  photo with AI" (`RecipeAssistant::dishPhoto()`, waits in `import-photos/{user id}/` on the private `local` disk, shown
  as a plain preview through `/import-photos/{filename}` (auth, own files only, `RecipePhotos::importPath()`) with
  "Remove AI photo", pruned after a day by `recipe-drafts:prune`), both added after the import through `RecipePhotos`
  (AI photo marked like below, a failure only warns)), 3. "Confirm". Drafts start at
  "Resolve" with cookbook and visibility there. The manual way
  (prompt for an external AI chat) is gone. The recipe edit page has "Create photo with AI" (`RecipeAssistant::image()`,
  also MCP `generate-recipe-photo`). Generated photos are stored like
  uploads with `is_ai_generated` and the source `OPENAI_IMAGE_SOURCE` (default "ChatGPT (OpenAI)").
* "Enhance with AI" (item action of the photo repeater on the edit page, only with `allows()` + update rights, not for
  trashed recipes): `RecipeAssistant::enhance()` sends the stored photo (JPEG/PNG/WebP) to `/images/edits` (multipart,
  `OPENAI_IMAGE_MODEL`, `size=auto`, JPEG `b64_json` answer) with the optional instruction, default
  `DEFAULT_ENHANCE_INSTRUCTION` (light, colors, sharpness, dish and composition unchanged). The result is an additional
  photo (`is_ai_generated`, `OPENAI_IMAGE_SOURCE`), the original stays; feature `image_edit`.
* Photos: `App\Services\RecipePhotos` (content-based MIME check JPEG/PNG/WebP/GIF via finfo, base64 data URIs,
  appending to `photos` under a row lock, the file is removed if saving fails) is shared by the JSON import, MCP and
  AI photos. MCP and AI photos (`add-recipe-photo`, `generate-recipe-photo`, "Create photo with AI", "Enhance with AI")
  stop at `RecipeJsonSchema::MAX_PHOTOS` (10, like the JSON import; checked before downloading or the AI request).
  Downloads go through `App\Services\PublicHttp` (also `RecipePage` and `ImageDownloader`): public http(s) hosts only
  (`isPublicIp()`: no private/reserved/CGNAT/benchmark/6to4/NAT64 ranges, IPv4-mapped IPv6 judged by its IPv4), no
  port/user, redirects checked again, cURL pinned to all checked IPs (CURLOPT_RESOLVE, IPv4 first), total timeout,
  `Accept-Encoding: identity` without decoding (no gzip bombs), transfer aborted above the size limit (Content-Length
  via `on_headers`, received bytes via `progress`); every transport error becomes `PublicUrlException`. Never add
  `stream` (Guzzle's stream handler rejects the `curl` option). Limits: base64 5 MB
  (`RecipeJsonSchema::MAX_PHOTO_BYTES`, travels inside the JSON payload), MCP URL downloads 10 MB
  (`RecipePhotos::MAX_DOWNLOAD_BYTES`, like the wizard upload), JSON import URLs 5 MB.
* Swiss spelling: `RECIPE_SPELLING` (`config/app.php` `recipe_spelling`, default `de_CH`, any other value such as
  `de_DE` disables it). `App\Services\SwissSpelling`: rule 9 of the import prompt, the MCP server instructions and
  the create/update tool descriptions ask for "ss" instead of "ß"; as safety net "ß" becomes "ss" in the AI import
  result (`RecipeAssistant::recipe()`), in pasted JSON and drafts (`ImportRecipe::parse()`, the textarea keeps its
  text) and in `create-recipe` (recipe texts except photos, author and web addresses, `create`/`map`/`conversions`
  names) and `update-recipe` (name, serving type, steps, tags). Independent of the setting, `RelationResolver` compares
  names with "ß" folded into "ss" (case-insensitive), so "Weißwein" matches "Weisswein" exactly.
* Usage: `RecipeAssistant` records every request in `ai_usages` (user, feature `recipe`/`image`/`image_edit`/
  `conversion`, model, input/output tokens, images) from the API's `usage`: basis for billing by usage (later through the
  AccountManager) and the dashboard (`AiUsageWidget` lists the requests per feature, `AiUsage::featureLabel()`).
* Tests: `phpunit.xml` forces an empty `OPENAI_API_KEY`; tests set a fake key and `Http::fake()`
  (`tests/Feature/AiRecipeTest.php`); `fakeDns()` in `TestCase` replaces DNS for `PublicHttp`. `PublicHttpTest` also runs
  the real cURL handler against `tests/fixtures/public-http-server.php` (`php -S` on loopback, a test subclass overrides
  `allows()`/`allowsPort()`/`addresses()`; production has no switch for it).

## MCP server (AI clients)

* `laravel/mcp`, `routes/ai.php`: `POST /mcp` (`App\Mcp\Servers\RecipesServer`, middleware `auth:api`,
  `EnsureMcpGrant`, `throttle:120,1`) and `Mcp::oauthRoutes()` (OAuth discovery under `/.well-known/*`, public
  dynamic client registration `POST /oauth/register`, throttled 10/min). Clients: Claude Code, Codex, OpenClaw,
  Claude Desktop … (remote MCP over HTTP + OAuth, no plugin). Public clients with PKCE, scope `mcp:use`
  (registered in `AppServiceProvider` next to `recipes`, otherwise our `tokensCan()` would drop it).
* Permissions: laravel/mcp knows only the one scope, so the approval screen (`passport/authorize.blade.php`,
  MCP clients detected by the `mcp:use` scope) shows checkboxes (`App\Mcp\McpPermission`: read, create,
  update, delete) and posts to `POST /oauth/authorize/mcp` (`McpAuthorizationController`): stores
  `mcp_grants` (user + OAuth client + permissions, `last_used_at`) and then calls Passport's approval.
  `EnsureMcpGrant` refuses tokens without `mcp:use` or without grant; `DenyMcpTokens` keeps AI client tokens
  off the JSON API. Tools extend `App\Mcp\Tools\RecipeTool` (`shouldRegister()` = grant has the permission);
  the user's own rights still apply (`visibleTo()`, `RecipePolicy`).
* Tools: `search-recipes`, `get-recipe`, `list-vocabulary`, `find-similar` (read: type + names → exact match,
  similar entries visible to the user or none, `RelationResolver::existing()`/`suggest()`), `get-recipe-format`,
  `create-recipe` (create: import JSON without photos; unknown values, also tags/attributes, stop with a draft
  link to the import wizard and their `suggestions` unless listed in `create` (agreed new names, requests for
  non-admins, admins are mailed) or `map` (`{"Rüebli": "Karotte"}`, existing entry by name); the deprecated
  `create_missing` only creates values without suggestions; optional `conversions` (`{"Food": {measure,
  density, piece_weights: {"Stück": 60}, season_months: [5, 6], storage_months: [1]}}`, all optional,
  `FoodConversions::validate()`, explained in `get-recipe-format` too) only for foods it creates; `on_duplicate`: `rename` (default, saves "Name (2)" …, answers `renamed` + `original_name`) or
  `error` (names the conflict, also when it is in the trash); never force deletes), `update-recipe`
  (update: fields, Markdown steps, existing tags; ingredients only in the app), `add-recipe-photo` (update +
  `RecipePolicy` update: `url` (public, max. 10 MB) or `data` (base64 data URI, max. 5 MB), optional `source`,
  `is_ai_generated`; appended to the photos), `generate-recipe-photo` (update, only listed with
  `RecipeAssistant::allows()`: `RecipeAssistant::image()`, hourly limit, returns the stored photo), `delete-recipe`
  (delete: soft delete).
* Page "Connected AI clients" (`App\Filament\Pages\ConnectedAiClients`, every user): connection guide
  per client, own grants with permissions (editable), last use, revoke (revokes the client's access and
  refresh tokens of the user and deletes the grant).
* Tests: `tests/Feature/McpServerTest.php` (`Passport::actingAs($user, ['mcp:use'], 'api', $client)` +
  `RecipesServer::tool()`).

## Dashboard

* Widgets in `app/Filament/Widgets` (discovered; Filament's info widget removed): `QuickStartWidget`
  (import/AI, new recipe, season calendar, AI clients), `RecentRecipesWidget` (own recipes, last changed),
  `SeasonWidget` (foods of the month used in recipes + visible seasonal recipes, own first),
  `MyCookbooksWidget` (own or member, recipe count). Admins only: `AdminOverviewWidget` (open requests with
  link to the filtered list, used foods without conversion/season data, users, AI requests and tokens this
  month, connected AI clients) and `AiUsageWidget` (per user this month). Tests: `DashboardTest`.

## Lookup Requests and Import Decisions

* Foods, units, ingredient attributes and tags use `Requestable` (`requested_by`, `requested_at`):
  non-admins create them in the import wizard as requests (`LookupType::createsRequest()`), usable
  in their recipe right away. Choosing "create" in the wizard shows the name (prefilled, editable; a name
  that already exists selects that entry instead) and for foods the ingredient attributes (tags, written into the JSON
  of every ingredient with that food; unknown ones then need a decision like other values), collapsible conversion fields (measure,
  density, piece weights) and season fields (`season_months`, `storage_months`, like `FoodForm`; state under
  `conversion`, `App\Services\FoodConversions`, months 1–12 distinct, checked again on import) with "Estimate
  conversions and season with AI" (`RecipeAssistant::conversions()`, one request for all new foods, strict
  schema incl. Swiss fresh/storage months, empty without Swiss season; months the user ticked are kept). The name is
  checked again on "Next"; a name given twice is created once (the first one's data wins), and data for an entry that
  already exists (also trashed or another user's request) is not stored: the wizard warns after the import, MCP lists
  it in `conversions_ignored`, the summary lists such names under "Existing entries used". Too long names stay creatable
  and show the error until shortened;
  admins see them in the Food resource when approving. Option lists, the import prompt, the API and the admin resources use
  `visibleTo($user)` (approved + own requests, admins all); recipes using a request show it to
  everyone. Requesting a name someone else requested reuses that entry (unique name index).
* Admins approve requests or merge them into an existing entry (`LookupRequests` table actions,
  `LookupMerger`: repoints ingredients and pivot rows, then deletes the source). The resources show
  the number of open requests as navigation badge. Every admin gets a mail (`LookupRequestedMail`,
  in their locale) when an import created requests. The wizard and `create-recipe` create new lookups
  and the recipe in one transaction and mail only after it: a failed import leaves no requests.
* An empty selection in the resolve step means "do not import" (no "Omit" toggle, no validation error; "Create … instead"
  sits below the selection): units, attributes and tags are dropped; an omitted food drops its
  ingredient (with alternatives) and a group left empty. Units and attributes only used on omitted
  ingredients need no decision (`isObsolete()` in the wizard, `isUsed()` in `RecipeImporter`).
  Categories, cookbooks and authors cannot be omitted; at least one ingredient must remain.

## Recipe names and trash

* Names are unique per cookbook, trashed recipes included (unique index `name` + `cookbook_id`); without
  cookbook per author, which only `App\Services\RecipeNames` checks (MariaDB lets NULLs repeat):
  `findDuplicate()`, `availableName()` ("Name (2)", "(3)" … within 100 chars), `rule()`.
  `RecipeImporter` checks the effective cookbook (explicit choice, else the JSON's) and author.
* Import wizard: "Resolve" starts with the editable name (filled from the JSON, kept when going back and
  forth) and checks it live against the chosen cookbook; a conflict shows a callout with "Use …" and, for
  a recipe in the trash the user may change (policy), "Delete permanently" / "Rename". The importer's
  own check stays as safety net.
* Recipe list: tabs "All" / "Trash" (no `TrashedFilter`), row actions Delete, Restore, "Restore under
  another name" and Force delete (`RecipeTrashActions`, also on the edit page), bulk actions per record
  authorized. Trashed recipes are read-only on the edit page.
* `RecipeObserver`: deleting trashes ingredients and groups with the recipe's timestamp and keeps their
  positions, restoring brings them back and is refused while a live recipe has the name; force deleting
  leaves rows to the FK cascades and deletes the photo directory `{id}/` on the `recipes` disk.
  Tests: `RecipeTrashTest`, `RecipeImportDuplicatesTest`.

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

## Unit conversion

* `units.measure` (`App\Enums\Measure`: mass/volume) + `units.base_amount` (g or ml per unit; EL 15 ml,
  TL 5 ml, Tasse 2 dl, Cup 2.4 dl). Units without measure are pieces (Stück, Bund, …) or vague amounts
  (Prise, Messerspitze, nach Belieben) and only convert through `food_unit_weights` (grams per piece unit
  per food). `foods.measure` is the fixed measure a food's amounts are converted to (empty: the unit's
  own), `foods.density` (g/ml) converts between mass and volume. Admins edit them in the Unit and Food
  resources; `LookupMerger` moves piece weights along.
* `App\Services\UnitConverter`: `factor()` (g/ml per unit), `changes()` (other measure or a non-metric
  unit), `volumeFactor()` (ml per unit, mass and piece weights through the density), `display()` (g < 1000 ≤ kg;
  ml < 100 ≤ dl < 1000 ≤ l; g/ml whole from 10, else 1 decimal). The API's `converted` uses `factor()` + `display()` only.
* Recipe page display modes (select `data-convert-units`, remembered in `localStorage` `recipeUnitMode`, default
  "Original"): Original; Metric (g/kg or ml/dl/l by `factor()`, the former "Convert"); Volume (ml/dl/l by
  `volumeFactor()`, units already in ml/dl/l stay); "Cups/EL/TL" and "Cups (US)" (`cupSystems()`: units `Tasse`
  or `Cup` + `Esslöffel` + `Teelöffel` by name, approved with volume; size and names from the units table, a
  mode without its units is not offered). `cups()` (mirrored by `cupAmount()` in `app.js`) picks the cup from ¼ cup,
  otherwise the tablespoon from 1, otherwise the teaspoon (by the larger amount of a range) and rounds to the
  nearest whole + fraction (cups ¼ ⅓ ½ ⅔ ¾, spoons ⅛ ¼ ½ ¾); an amount rounding to 0 (a drop) stays as entered.
* `pageConversion()` per ingredient (`metric` = `factor()`, `metric_changes`, `volume`, `volume_changes`; null
  without amount, for pieces without weight and vague amounts: they always stay) and `pageModes()` (the modes
  that change at least one ingredient; the select only shows with more than "Original") feed `recipeServings`
  in `app.js`, which multiplies with the servings (`convertedAmount()` mirrors `display()`). Converted rows
  show the original amount in brackets (not when it reads the same), rows the chosen mode cannot convert (no
  density/piece weight) stay with "(not convertible)", and a warning like the servings one.
* Import (`RecipeImporter`, so wizard, MCP and drafts): amounts of kg, l or dl needing more than two decimals move to
  the next smaller approved metric unit where they fit (`UnitConverter::precise()`: 1/8 l = 0.125 l becomes 1.25 dl);
  the import prompt asks for the same.
* Data: `database/data/food-conversions.php` (units by name, foods by name: measure, density, piece
  weights). `ddev artisan foods:conversions {--dry-run} {--force}` assigns it to exactly matching names:
  without `--force` every empty field (measure, density, base amount, piece weight) is filled on its own and
  fields already set to another value are kept (listed as "kept (is …)", also where the data has no density);
  `--force` overwrites them (a data density of null clears it). It lists data names without a match. Data
  migrations run it on deploy (`2026_11_07_090100_assign_food_conversions`,
  `2026_11_15_090000_fill_missing_food_conversions`); add another one when the data grows. The data covers
  the foods used in recipes: densities measured loosely in a spoon/cup, null where a volume makes no sense
  (whole meat, sausages, bread, whole fruit/vegetables, salads, dough, chocolate bars). Foods whose units
  mean different things (Kaffee, Gemüsebouillon: liquid or powder) are left out on purpose.

## Number format

* Decimals follow the browser's regional variant of the app language (`de-CH` 1.5, `de-DE` 1,5, otherwise the app
  locale): `SetLocale::numberLocale()` (Accept-Language) for PHP (`Number::format(..., locale:)` in the Food and Unit
  lists), `numberLocale()` (`navigator.languages`) in `app.js` for the recipe page. The API keeps the user's locale.

## Filament panel

* `unsavedChangesAlerts()`: leaving a form with unsaved changes asks first. The recipe edit page shows an info box
  above the form and a blue bar beside every changed field (`filament/unsaved-changes.blade.php`, render hook
  `PAGE_HEADER_WIDGETS_AFTER` scoped to `EditRecipe`; Alpine compares `$wire.data` with a snapshot taken after the
  fields initialised and after each save).
* The rich editor's JavaScript wraps inline content of list items in a paragraph on load; tight Markdown lists (the
  import) would therefore look changed right away. `RecipeForm` gives `instructions` the state cast
  `ListItemParagraphs`, which does the same on the server, so a freshly loaded form is clean.
* "Add to …" buttons quote the name (`lang/vendor/filament-forms/*/components.php`: de `Zu «…» hinzufügen`, en
  `Add to "…"`).

## Mail footer

- Easter egg: "All rights reserved." in the mail footer (published `resources/views/vendor/mail/{html,text}/message.blade.php`, nothing else of the mail views) links to `<ACCOUNT_MANAGER_URL>/easter-egg` (the page and the video live only in the AccountManager) via `App\Support\EasterEgg::url()`; without `ACCOUNT_MANAGER_URL` it stays plain text. Tests: `EasterEggMailTest`.

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
Tests never write to the real storage: `TestCase` fakes the `local` disk, tests using the `recipes` disk fake it.

Important test areas:

* authentication
* policies
* cookbook sharing and invitations
* households (membership, roles, last admin, invitations, driver) and cookbook access through households
* households from the AccountManager (webhook signature, idempotency, versions, sync, import, AccountManager down)
* entitlements from the AccountManager (snapshots, admin source, blocked access, user.deleted, sync, AccountManager down)
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
