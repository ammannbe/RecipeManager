<?php

namespace Tests\Feature;

use App\Models\Author;
use App\Models\Category;
use App\Models\Cookbook;
use App\Models\Food;
use App\Models\IngredientAttribute;
use App\Models\IngredientGroup;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\AuthorSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DatabaseSeederTest extends TestCase
{
    public function test_the_author_seeder_creates_authors_with_linked_users(): void
    {
        $this->seed(AuthorSeeder::class);

        $this->assertGreaterThan(0, Author::query()->count());
        $this->assertGreaterThan(0, User::query()->count());

        $orphans = User::query()
            ->whereNotIn('author_id', Author::query()->select('id'))
            ->count();

        $this->assertSame(0, $orphans);
    }

    public function test_the_author_seeder_creates_an_admin_from_the_mail_config(): void
    {
        $this->seed(AuthorSeeder::class);

        $admin = User::query()->where('email', config('mail.from.address'))->first();

        $this->assertNotNull($admin);
        $this->assertTrue((bool) $admin->admin);
        $this->assertSame(config('mail.from.name'), $admin->author->name);
    }

    public function test_the_author_seeder_is_idempotent_for_the_configured_admin(): void
    {
        $this->seed(AuthorSeeder::class);
        $this->seed(AuthorSeeder::class);

        $this->assertSame(
            1,
            User::query()->where('email', config('mail.from.address'))->count(),
        );
    }

    /**
     * @return array<string, array{0: Factory<covariant Model>, 1: int}>
     */
    public static function nameFactories(): array
    {
        return [
            'author' => [Author::factory(), 50],
            'category' => [Category::factory(), 50],
            'food' => [Food::factory(), 50],
            'unit' => [Unit::factory(), 20],
            'tag' => [Tag::factory(), 20],
            'attribute' => [IngredientAttribute::factory(), 40],
            'cookbook' => [Cookbook::factory(), 20],
            'recipe' => [Recipe::factory(), 100],
        ];
    }

    /**
     * Faker's word pool is smaller than the seeded row counts, so the name factories
     * have to stay unique well past it and still fit their column.
     *
     * @param  Factory<covariant Model>  $factory
     */
    #[DataProvider('nameFactories')]
    public function test_name_factories_stay_unique_and_fit_their_column(Factory $factory, int $maxLength): void
    {
        $names = $factory->count(60)->create()->pluck('name');

        $this->assertCount(60, $names->unique(), 'produced duplicate names');
        $this->assertLessThanOrEqual(
            $maxLength,
            $names->map(fn (string $name): int => mb_strlen($name))->max(),
            "produced a name longer than {$maxLength} characters",
        );
    }

    public function test_ingredient_group_names_stay_unique_within_a_recipe(): void
    {
        $recipe = Recipe::factory()->create();

        $groups = IngredientGroup::factory()->count(30)->create(['recipe_id' => $recipe->id]);

        $this->assertCount(30, $groups->pluck('name')->unique());
        $this->assertLessThanOrEqual(20, $groups->map(fn (IngredientGroup $g): int => mb_strlen((string) $g->name))->max());
    }
}
