<?php

namespace App\Providers;

use App\Listeners\AcceptPendingCookbookInvitations;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        require_once app_path('helpers.php');

        Event::listen(Registered::class, AcceptPendingCookbookInvitations::class);
        Event::listen(Verified::class, AcceptPendingCookbookInvitations::class);

        Blade::directive('nl2br', function (string $expression) {
            return "<?php echo nl2br(e($expression)); ?>";
        });

        // The "recipes" disk has no public URL; route Filament's FileUpload previews through the authorized photo route.
        Storage::disk('recipes')->buildTemporaryUrlsUsing(function (string $path) {
            [$recipe, $filename] = array_pad(explode('/', $path, 2), 2, null);

            if ($recipe === null || $filename === null) {
                return '';
            }

            return route('recipes.photo', ['recipe' => $recipe, 'filename' => $filename]);
        });
    }
}
