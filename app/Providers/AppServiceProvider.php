<?php

namespace App\Providers;

use App\Listeners\AcceptPendingCookbookInvitations;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Events\Verified;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Passport;
use Symfony\Component\HttpFoundation\Response;

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

        $this->configurePassport();
    }

    /**
     * OAuth for companion apps like Shoppinglist (authorization code grant, see AGENTS.md).
     */
    private function configurePassport(): void
    {
        // Clients may ask for "recipes" or for no scope at all; both grant the same access.
        Passport::tokensCan([
            'recipes' => 'Read the recipes you can see and save new private recipes',
        ]);

        Passport::tokensExpireIn(now()->addHour());
        Passport::refreshTokensExpireIn(now()->addDays(90));

        Passport::authorizationView(function (array $parameters): Response {
            /** @var User $user */
            $user = $parameters['user'];

            // Same rule as the Filament panel: no access before the e-mail address is verified.
            if (! $user->hasVerifiedEmail()) {
                return redirect()->guest(route('filament.app.auth.email-verification.prompt'));
            }

            return response()->view('passport.authorize', $parameters);
        });
    }
}
