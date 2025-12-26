<?php

namespace App\Providers;

use App\Models\Author;
use App\Models\Cookbook;
use App\Models\Ingredient;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Models\User;
use App\Observers\AuthorObserver;
use App\Observers\CookbookObserver;
use App\Observers\IngredientGroupObserver;
use App\Observers\IngredientObserver;
use App\Observers\RecipeObserver;
use App\Observers\UserObserver;
use Illuminate\Support\ServiceProvider;

class ObserverServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Author::observe(AuthorObserver::class);

        Cookbook::observe(CookbookObserver::class);

        Ingredient::observe(IngredientObserver::class);

        IngredientGroup::observe(IngredientGroupObserver::class);

        Recipe::observe(RecipeObserver::class);

        User::observe(UserObserver::class);
    }
}
