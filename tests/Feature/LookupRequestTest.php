<?php

namespace Tests\Feature;

use App\Filament\Resources\Foods\FoodResource;
use App\Filament\Resources\Foods\Pages\ListFoods;
use App\Filament\Resources\Tags\Pages\ListTags;
use App\Models\Food;
use App\Models\Ingredient;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use App\Services\Lookup\LookupMerger;
use Livewire\Livewire;
use Tests\TestCase;

class LookupRequestTest extends TestCase
{
    private function requested(string $name, User $user): Food
    {
        return Food::query()->forceCreate(['name' => $name, 'requested_by' => $user->id, 'requested_at' => now()]);
    }

    public function test_pending_requests_are_hidden_from_other_users_in_lists_and_the_api(): void
    {
        $requester = User::factory()->create(['admin' => false]);
        $pending = $this->requested('Yuzu', $requester);
        Food::factory()->create(['name' => 'Apfel']);
        $other = User::factory()->create(['admin' => false]);

        $this->actingAs($other);
        Livewire::test(ListFoods::class)->assertCanNotSeeTableRecords([$pending])->assertCountTableRecords(1);
        $this->get('/admin/foods/'.$pending->id.'/edit')->assertNotFound();

        $this->actingAs($requester);
        Livewire::test(ListFoods::class)->assertCanSeeTableRecords([$pending]);

        $this->assertSame(['Apfel'], Food::query()->visibleTo(null)->pluck('name')->all());
    }

    public function test_an_admin_approves_a_request(): void
    {
        $pending = $this->requested('Yuzu', User::factory()->create(['admin' => false]));
        $this->actingAs(User::factory()->admin()->create());

        $this->assertSame('1', FoodResource::getNavigationBadge());

        Livewire::test(ListFoods::class)
            ->assertTableActionVisible('approve', $pending)
            ->callTableAction('approve', $pending);

        $this->assertNull($pending->fresh()->requested_at);
        $this->assertNull($pending->fresh()->requested_by);
        $this->assertNull(FoodResource::getNavigationBadge());
    }

    public function test_non_admins_cannot_approve_or_merge(): void
    {
        $user = User::factory()->create(['admin' => false]);
        $pending = $this->requested('Yuzu', $user);
        $this->actingAs($user);

        Livewire::test(ListFoods::class)
            ->assertTableActionHidden('approve', $pending)
            ->assertTableActionHidden('merge', $pending);
    }

    public function test_an_admin_merges_a_request_into_an_existing_food(): void
    {
        $pending = $this->requested('Zitrone (Bio)', User::factory()->create(['admin' => false]));
        $zitrone = Food::factory()->create(['name' => 'Zitrone']);
        $ingredient = Ingredient::factory()->create(['food_id' => $pending->id]);
        $this->actingAs(User::factory()->admin()->create());

        Livewire::test(ListFoods::class)
            ->callTableAction('merge', $pending, ['target' => $zitrone->id])
            ->assertHasNoTableActionErrors();

        $this->assertSame($zitrone->id, $ingredient->fresh()->food_id);
        $this->assertDatabaseMissing('foods', ['id' => $pending->id]);
    }

    public function test_merging_tags_keeps_one_row_per_recipe(): void
    {
        $source = Tag::factory()->create(['name' => 'Schnell']);
        $target = Tag::factory()->create(['name' => 'Rasch']);
        $both = Recipe::factory()->create();
        $both->tags()->attach([$source->id, $target->id]);
        $one = Recipe::factory()->create();
        $one->tags()->attach($source->id);

        app(LookupMerger::class)->merge($source, $target);

        $this->assertSame([$target->id], $both->tags()->pluck('tags.id')->all());
        $this->assertSame([$target->id], $one->tags()->pluck('tags.id')->all());
        $this->assertDatabaseMissing('tags', ['id' => $source->id]);
    }

    public function test_tags_list_hides_pending_requests_from_the_public_page(): void
    {
        Tag::query()->forceCreate(['name' => 'Geheim', 'requested_by' => User::factory()->create()->id, 'requested_at' => now()]);
        Tag::factory()->create(['name' => 'Offen']);

        $this->get('/')->assertSee('Offen')->assertDontSee('Geheim');

        $this->actingAs(User::factory()->admin()->create());
        Livewire::test(ListTags::class)->assertCountTableRecords(2);
    }
}
