<?php

namespace App\Providers;

use App\Models\Tag;
use App\Models\Author;
use App\Models\Recipe;
use App\Models\Food;
use App\Models\Unit;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Ingredient;
use App\Models\RatingCriterion;
use Illuminate\Support\ServiceProvider;
use App\Models\IngredientGroup;
use App\Models\IngredientAttribute;
use App\Observers\AuthorObserver;

class ObserverServiceProvider extends ServiceProvider
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
        Food::observe('App\Observers\FoodObserver');
        IngredientGroup::observe('App\Observers\IngredientGroupObserver');
        IngredientAttribute::observe('App\Observers\IngredientAttributeObserver');
        Ingredient::observe('App\Observers\IngredientObserver');
        Unit::observe('App\Observers\UnitObserver');

        RatingCriterion::observe('App\Observers\RatingCriterionObserver');

        Category::observe('App\Observers\CategoryObserver');
        Recipe::observe('App\Observers\RecipeObserver');
        Tag::observe('App\Observers\TagObserver');
        Cookbook::observe('App\Observers\CookbookObserver');

        Author::observe(AuthorObserver::class);
    }
}
