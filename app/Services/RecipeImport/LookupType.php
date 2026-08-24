<?php

namespace App\Services\RecipeImport;

use App\Models\Author;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Food;
use App\Models\IngredientAttribute;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

enum LookupType: string
{
    case Food = 'food';
    case Unit = 'unit';
    case Tag = 'tag';
    case Category = 'category';
    case Cookbook = 'cookbook';
    case Attribute = 'attribute';
    case Author = 'author';

    /**
     * @return class-string<Model>
     */
    public function model(): string
    {
        return match ($this) {
            self::Food => Food::class,
            self::Unit => Unit::class,
            self::Tag => Tag::class,
            self::Category => Category::class,
            self::Cookbook => Cookbook::class,
            self::Attribute => IngredientAttribute::class,
            self::Author => Author::class,
        };
    }

    /**
     * A query including trashed rows, which still occupy the unique name index.
     *
     * @return Builder<covariant Model>
     */
    public function query(): Builder
    {
        return match ($this) {
            self::Food => Food::query()->withTrashed(),
            self::Unit => Unit::query()->withTrashed(),
            self::Tag => Tag::query()->withTrashed(),
            self::Category => Category::query()->withTrashed(),
            self::Cookbook => Cookbook::query()->withTrashed(),
            self::Attribute => IngredientAttribute::query()->withTrashed(),
            self::Author => Author::query()->withTrashed(),
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Food => __('Food'),
            self::Unit => __('Unit'),
            self::Tag => __('Tag'),
            self::Category => __('Category'),
            self::Cookbook => __('Cookbook'),
            self::Attribute => __('Ingredient attribute'),
            self::Author => __('Author'),
        };
    }

    public function maxLength(): int
    {
        return match ($this) {
            self::Food => RecipeJsonSchema::MAX_FOOD_NAME,
            self::Unit => RecipeJsonSchema::MAX_UNIT_NAME,
            self::Tag => RecipeJsonSchema::MAX_TAG_NAME,
            self::Category => RecipeJsonSchema::MAX_CATEGORY_NAME,
            self::Cookbook => RecipeJsonSchema::MAX_COOKBOOK_NAME,
            self::Attribute => RecipeJsonSchema::MAX_ATTRIBUTE_NAME,
            self::Author => RecipeJsonSchema::MAX_AUTHOR_NAME,
        };
    }

    /**
     * Whether the value may simply be dropped: units are nullable on an ingredient, and
     * attributes and tags are pivot rows. A food or category is mandatory.
     */
    public function isOptional(): bool
    {
        return match ($this) {
            self::Unit, self::Attribute, self::Tag => true,
            default => false,
        };
    }

    /**
     * Mirrors the create permissions of RecipeForm: everyone may add cookbooks and
     * categories on the fly, the remaining lookup tables are admin only.
     */
    public function canBeCreatedBy(?User $user): bool
    {
        if ($user === null) {
            return false;
        }

        return match ($this) {
            self::Cookbook, self::Category => true,
            self::Author => false,
            default => (bool) $user->admin,
        };
    }
}
