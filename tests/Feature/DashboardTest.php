<?php

namespace Tests\Feature;

use App\Filament\Widgets\AdminOverviewWidget;
use App\Filament\Widgets\AiUsageWidget;
use App\Filament\Widgets\MyCookbooksWidget;
use App\Filament\Widgets\QuickStartWidget;
use App\Filament\Widgets\RecentRecipesWidget;
use App\Filament\Widgets\SeasonWidget;
use App\Models\AiUsage;
use App\Models\Cookbook;
use App\Models\Food;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use App\Services\Season;
use Filament\Facades\Filament;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    public function test_the_dashboard_shows_the_user_widgets(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(Filament::getPanel('app')->getUrl())
            ->assertOk()
            // @phpstan-ignore method.notFound (Livewire macro)
            ->assertSeeLivewire(QuickStartWidget::class)
            ->assertSeeLivewire(RecentRecipesWidget::class)
            ->assertSeeLivewire(SeasonWidget::class)
            ->assertSeeLivewire(MyCookbooksWidget::class)
            ->assertDontSeeLivewire(AdminOverviewWidget::class)
            ->assertDontSeeLivewire(AiUsageWidget::class);
    }

    public function test_admins_get_the_admin_widgets(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get(Filament::getPanel('app')->getUrl())
            ->assertOk()
            // @phpstan-ignore method.notFound (Livewire macro)
            ->assertSeeLivewire(AdminOverviewWidget::class)
            ->assertSeeLivewire(AiUsageWidget::class);
    }

    public function test_own_recipes_and_cookbooks_are_listed(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $own = Recipe::factory()->create(['author_id' => $user->author_id, 'cookbook_id' => null]);
        $foreign = Recipe::factory()->create(['is_public' => true, 'cookbook_id' => null]);
        $cookbook = Cookbook::factory()->create(['author_id' => $user->author_id]);
        $other = Cookbook::factory()->create();

        Livewire::test(RecentRecipesWidget::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$foreign]);

        Livewire::test(MyCookbooksWidget::class)
            ->assertCanSeeTableRecords([$cookbook])
            ->assertCanNotSeeTableRecords([$other]);
    }

    public function test_the_season_widget_names_foods_and_recipes_of_the_month(): void
    {
        $month = Season::currentMonth();
        $this->actingAs(User::factory()->create());
        $food = Food::factory()->create(['name' => 'Saisonrüebli', 'season_months' => [$month]]);
        $recipe = Recipe::factory()->create(['name' => 'Rüeblitorte', 'is_public' => true, 'cookbook_id' => null]);
        Ingredient::factory()->create(['recipe_id' => $recipe->id, 'food_id' => $food->id, 'ingredient_group_id' => null, 'ingredient_id' => null, 'position' => 1]);

        Livewire::test(SeasonWidget::class)->assertSee('Saisonrüebli')->assertSee('Rüeblitorte');
    }

    public function test_admins_see_requests_and_ai_usage_per_user(): void
    {
        $this->actingAs(User::factory()->admin()->create());
        $user = User::factory()->create();
        Tag::factory()->create(['requested_by' => $user->id, 'requested_at' => now()]);
        AiUsage::query()->create(['user_id' => $user->id, 'feature' => 'recipe', 'model' => 'm', 'input_tokens' => 1000, 'output_tokens' => 200]);
        AiUsage::query()->create(['user_id' => $user->id, 'feature' => 'image', 'model' => 'm', 'images' => 1]);
        AiUsage::query()->create(['user_id' => $user->id, 'feature' => 'recipe', 'model' => 'm', 'input_tokens' => 5])->forceFill(['created_at' => now()->subMonths(2)])->save();

        Livewire::test(AdminOverviewWidget::class)->assertSee('Open requests')->assertSee('Tags: 1');

        Livewire::test(AiUsageWidget::class)
            ->assertCanSeeTableRecords([$user])
            ->assertTableColumnStateSet('requests', 2, $user)
            ->assertTableColumnStateSet('input_tokens', 1000, $user)
            ->assertTableColumnStateSet('images', 1, $user);
    }
}
