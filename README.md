# IMPORTANT

This is a complete rewrite from [RecipeManagerApi](https://github.com/ammannbe/RecipeManagerApi) and [RecipeManagerWeb](https://github.com/ammannbe/RecipeManagerWeb) which in earlier days based on a laravel REST API and a Nuxt.js web frontend.

This setup was very complicated, error prone and the existing codes had a lot of bugs. That's why I decided to adopt the code to a normal laravel app based on [livewire](https://livewire.laravel.com/) and [flux](https://fluxui.dev/).

# RecipeManager

A tool to manage your families and friends recipes like a chef.
Written with the PHP Framework [Laravel](https://laravel.com/).

***IMAGE COMING SOON***

## Why is this so awesome?

-   **Manage your recipes** - You and your friends can save, edit and delete recipes.
-   **Share recipes** - You can share recipes by one click via Telegram or E-Mail.
-   **Calculate servings** - Calculate servings directly in the recipe on the fly.
-   **Reuse recipe properties** - ..like author, category, tags, ingredients, units and more.

## What features are planned?

-   Nutrition informations
-   Rating system (the API code is already written ;-) )
-   A feature you think is missing...

## Getting Started

Get the latest [release](https://github.com/ammannbe/RecipeManager) or clone the repo with

```bash
git clone https://github.com/ammannbe/RecipeManager.git
```

### Prerequisites

-   LAMP Stack or Docker
-   Requirements for [laravel](https://laravel.com/docs)
-   GD and WebP for image manipulation
-   Composer
-   NPM
-   Redis (optional but recommended)

### Installation and Update

#### Manual installation

- Install composer packages: `composer install --no-interaction`
    - Alternatively with docker: `docker run --rm --interactive --tty -u $(id -u):$(id -g) -v $(pwd):/app composer install --ignore-platform-reqs`
- Copy .env.example to .env and modify it according your needs
- **On development only** start the server `ddev start`
- Generate storage symlink `php artisan storage:link` or `ddev artisan storage:link`
- Generate an app key `php artisan key:generate` or `ddev artisan key:generate`
- Migrate the database `php artisan migrate` or `ddev artisan migrate`
- Install NPM packages: `npm install --no-save` or `ddev npm install --no-save`
- Build assets: `npm run build` or `ddev npm run build`
- **On production only** setup the queue worker: `php artisan queue:work` (e.g. via systemd or a separate docker container)
- **On production only** add following to your crontab:

```bash
  *  *  *  *  *  www-data   cd /path-to-the-project && php artisan schedule:run >> /dev/null 2>&1
```

#### Development deployment/update

- If not already done, [install](#installation) everything
- Check `.env.example` for changes
- Launch the project `ddev launch`
- Install composer packages `ddev composer install`
- Migrate the database `ddev artisan migrate`
- Run the queue worker `ddev artisan queue:work`
- Run the scheduler `ddev artisan schedule:work`
- Run Vite.js: `ddev npm run dev`
- Clear caches:

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

#### Production deployment/update

- If not already done, [install](#installation) everything
- Check `.env.example` for changes
- Install and optimize composer packages `composer install --optimize-autoloader --no-dev`
- Migrate the database `php artisan migrate`
- Install NPM packages: `npm install --no-save`
- Build assets: `npm run build`
- Clear caches:

```bash
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

## Translations

All application related files are translated with [laravel-translation-manager](https://github.com/barryvdh/laravel-translation-manager) and [laravel-lang/lang](https://github.com/Laravel-Lang/lang)

You should run these commands only on a development machine.

Setup the translations table:

```bash
ddev artisan vendor:publish --provider="Barryvdh\TranslationManager\ManagerServiceProvider" --tag=migrations
ddev artisan migrate
```

Run these commands for making translations:

-   Cleanup your database `ddev artisan translations:reset`
-   Import translations `ddev artisan translations:import`
-   Find new translations `ddev artisan translations:find`
-   Open [http://\<project\>.ddev.site/translations](http://<project>.ddev.site/translations) in a browser
-   Now make your translations
-   Export & generate translations `ddev artisan translations:export --all`

## IDE helpers

You get better IDE IntelliSense support with the [laravel-ide-helper](https://github.com/barryvdh/laravel-ide-helper) package.

Simply run the command `ddev composer run ide-helper`

After that, you should run the commands from [Testing / Code Quality](#testing-/-code-quality).

## Testing / Code Quality

Seed the database with test data (for development only)

```bash
# Seed the database with test data
ddev artisan db:seed

# OR: freshly migrate and seed the database
ddev artisan migrate:fresh --seed

# The secret of the seeded users is 'password'
```

Before every commit, run the composer quality command:

```bash
ddev composer run quality
```

This will execute the PHP code style fixer, static code analytics and the IDE helper.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Authors

-   **Benjamin Ammann** - _Initial work_ - [ammannbe](https://github.com/ammannbe)

## License

This project is licensed under the AGPLv3 or later - see the [LICENSE](LICENSE) file for details

## Gallery

***GALLERY COMING SOON***
