<?php

namespace App\Providers;

use Illuminate\Support\Facades\Blade;
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

        Blade::directive('emdash', function () {
            return '<?php echo emdash(); ?>';
        });

        Blade::directive('nl2br', function (string $expression) {
            return "<?php echo nl2br(e($expression)); ?>";
        });
    }
}
