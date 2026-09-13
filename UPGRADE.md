# Upgrade Guide: v9.x → v10.x

## Summary

This upgrade **drops the ratings tables** and **moves all recipe photos** out of the
public directory. Both happen during the migration and cannot be undone without a
backup. Read the prerequisites before you start.

## Prerequisites

- **Back up your database.** The `ratings` and `rating_criteria` tables are dropped.
  Ratings were never creatable through the interface, so this most likely only affects
  seeded demo data — but the data is gone afterwards either way.
- **Back up the entire `storage` directory.** Photos are moved from
  `storage/app/public/recipes` to `storage/app/private/recipes`.
- If using Docker: ensure the complete app directory is mounted into the container,
  because files are moved during the upgrade.

Example `docker-compose` mapping:
```yaml
services:
  app:
    volumes:
      - ./storage/app:/var/www/html/storage/app
```

- Pull the latest code:
```bash
git pull origin <branch>
```

## Upgrade Steps

1. Install project dependencies:
```bash
composer install --no-dev --optimize-autoloader
```

2. Run the migrations **before** the new code serves traffic. The photos are relocated
   by a migration, so images will not load while the new configuration already points
   at a directory the files have not reached yet:
```bash
php artisan migrate --force
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

3. Rebuild the frontend assets:
```bash
npm ci
npm run build
```

## Post-upgrade tasks

- Open a recipe and confirm its images still load. They are now served through the
  application rather than by the web server directly.
- Confirm that a recipe you consider private is not reachable while logged out.
- Check the recipe list for the new **Public** column and set the flag where you want
  recipes to be visible to visitors.

## Breaking changes

### Ratings were removed

The `ratings` and `rating_criteria` tables are dropped. Ratings could only ever be
created by the database seeder, so the average shown on the recipe list was always
empty in practice.

### Recipe visibility is now explicit

A recipe used to be public exactly when it had no cookbook. It now carries its own
`is_public` flag, and a whole cookbook can be published at once. Existing recipes are
migrated so that today's visibility is preserved exactly: everything without a cookbook
becomes public, everything else stays private.

From now on a recipe is visible to visitors when **either** its own flag is set **or**
its cookbook is published.

### Photo URLs changed

Photos previously lived under `storage/app/public/recipes` and were served straight by
the web server, which meant images of private recipes were readable by anyone who knew
the URL. They now live in `storage/app/private/recipes` and are served through the
application, so the same rules apply to an image as to its recipe.

Old `/storage/recipes/...` links no longer work. Bookmarks or external links pointing
at them will stop resolving.

## Notes & Tips

- The photo migration is safe to re-run: it skips recipes that have already been moved,
  and `migrate:rollback` moves the files back.
- No configuration changes are required. To offer languages beyond German and English,
  add them to `locales` in `config/app.php` together with a matching file in `lang/`.
- Invitations to shared cookbooks expire after 14 days. A scheduled command removes
  expired ones, so make sure the scheduler is running (see the crontab entry in the
  README).
