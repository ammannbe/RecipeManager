# Upgrade Guide: v8.x → v9.x

## Summary

This upgrade moves/normalizes recipe instruction files and removes the old images folder. Follow these steps to avoid data loss.

## Prerequisites

- Backup your database and the entire storage directory.
- If using Docker: ensure the complete app directory is mounted into the container because files will be moved during the upgrade.

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

2. Install the required package (must be present before running migrations). The migrations convert recipe instructions from Markdown to HTML and require `spatie/laravel-markdown`:
```bash
composer require spatie/laravel-markdown
```

3. Run the deploy script:
```bash
./project-deploy.sh
```

4. Post-migration cleanup: the old images folder can be removed:
```bash
rm -rf storage/app/images
```

If running in Docker, from the host:
```bash
docker exec -it <app-container> rm -rf /var/www/html/storage/app/images
```
Or using `ddev`:
```bash
ddev ssh -c 'rm -rf storage/app/images'
```

## Post-upgrade tasks

- Uninstall `spatie/laravel-markdown`:
```bash
composer require spatie/laravel-markdown
```

- Verify:
  - Open the app and spot-check several recipes to confirm instructions render as HTML.
  - Confirm images and other attachments still resolve correctly.

## Notes & Tips

- The key change is that recipe instructions are parsed from Markdown to HTML during migrations; `spatie/laravel-markdown` must be installed beforehand.
- Mounting the full app directory in Docker is critical to avoid data loss because files may be moved by migrations.
- After successful verification, you can permanently remove `storage/app/images` to reclaim space.
