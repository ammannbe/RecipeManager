<?php

namespace App\Providers;

use App\Models\Recipes\Tag;
use App\Models\Author;
use App\Models\Ratings\Rating;
use App\Models\Recipes\Recipe;
use App\Models\Ingredients\Food;
use App\Models\Ingredients\Unit;
use App\Models\Recipes\Category;
use App\Models\Recipes\Cookbook;
use App\Models\Ingredients\Ingredient;
use App\Models\Ratings\RatingCriterion;
use Illuminate\Support\ServiceProvider;
use App\Models\Ingredients\IngredientGroup;
use App\Models\Ingredients\IngredientAttribute;
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
        Food::observe('App\Observers\Ingredients\FoodObserver');
        IngredientGroup::observe('App\Observers\Ingredients\IngredientGroupObserver');
        IngredientAttribute::observe('App\Observers\Ingredients\IngredientAttributeObserver');
        Ingredient::observe('App\Observers\Ingredients\IngredientObserver');
        Unit::observe('App\Observers\Ingredients\UnitObserver');

        Rating::observe('App\Observers\Ratings\RatingObserver');
        RatingCriterion::observe('App\Observers\Ratings\RatingCriterionObserver');

        Category::observe('App\Observers\Recipes\CategoryObserver');
        Recipe::observe('App\Observers\Recipes\RecipeObserver');
        Tag::observe('App\Observers\Recipes\TagObserver');
        Cookbook::observe('App\Observers\Recipes\CookbookObserver');

        Author::observe(AuthorObserver::class);
    }
}
