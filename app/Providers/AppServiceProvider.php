<?php

namespace App\Providers;

use App\Listeners\AcceptPendingCookbookInvitations;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
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
    }
}
