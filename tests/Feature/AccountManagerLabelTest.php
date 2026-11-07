<?php

namespace Tests\Feature;

use App\Filament\Resources\Households\Pages\ListHouseholds;
use App\Households\AccountManagerHouseholds;
use App\Households\AccountManagerLabel;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Once;
use Livewire\Livewire;
use Tests\TestCase;

class AccountManagerLabelTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string, mixed>|null answer of the fake AccountManager, null: unreachable */
    private ?array $info = ['name' => 'Narrenhaus-Konto', 'households_url' => 'https://konto.example.com/households'];

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'households.driver' => 'account-manager',
            'households.account_manager.url' => 'https://account-manager.test',
            'households.account_manager.label' => null,
        ]);

        // Http::fake() stubs add up and the first one wins, so a single fake answers with $this->info.
        Http::fake(function () {
            if ($this->info === null) {
                throw new ConnectionException('The AccountManager is down.');
            }

            return Http::response($this->info);
        });
    }

    public function test_the_env_label_wins_without_asking_the_account_manager(): void
    {
        config(['households.account_manager.label' => ' Mein Konto ']);

        $this->assertSame('Mein Konto', AccountManagerLabel::get());
        Http::assertNothingSent();
    }

    public function test_the_name_and_households_page_come_from_the_account_manager_and_are_cached(): void
    {
        $this->assertSame('Narrenhaus-Konto', AccountManagerLabel::get());
        $this->assertSame('https://konto.example.com/households', AccountManagerHouseholds::portalUrl());
        Http::assertSentCount(1);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://account-manager.test/api/info');

        Once::flush();
        $this->info = ['name' => 'Changed', 'households_url' => null];

        $this->assertSame('Narrenhaus-Konto', AccountManagerLabel::get());
        Http::assertSentCount(1);

        // After a day it is asked again.
        $this->travel(25)->hours();
        Once::flush();

        $this->assertSame('Changed', AccountManagerLabel::get());
        $this->assertSame('https://account-manager.test/households', AccountManagerHouseholds::portalUrl());
    }

    public function test_it_falls_back_to_account_while_the_account_manager_is_down_and_retries_soon(): void
    {
        $this->info = null;

        $this->assertSame(__('Account'), AccountManagerLabel::get());
        $this->assertSame('https://account-manager.test/households', AccountManagerHouseholds::portalUrl());

        $this->travel(11)->minutes();
        Once::flush();
        $this->info = ['name' => 'Narrenhaus-Konto', 'households_url' => null];

        $this->assertSame('Narrenhaus-Konto', AccountManagerLabel::get());
    }

    public function test_unusable_answers_fall_back(): void
    {
        $this->info = ['name' => '  ', 'households_url' => 'javascript:alert(1)'];

        $this->assertSame(__('Account'), AccountManagerLabel::get());
        $this->assertSame('https://account-manager.test/households', AccountManagerHouseholds::portalUrl());
    }

    public function test_the_local_driver_never_asks(): void
    {
        config(['households.driver' => 'local']);

        $this->assertSame(__('Account'), AccountManagerLabel::get());
        $this->assertNull(AccountManagerHouseholds::portalUrl());
        Http::assertNothingSent();
        $this->assertNull(Cache::get('account-manager.info'));
    }

    public function test_the_households_page_names_the_account_manager(): void
    {
        app()->setLocale('de');
        $this->actingAs(User::factory()->create(['admin' => false]));

        Livewire::test(ListHouseholds::class)
            ->assertSee('Haushalte im «Narrenhaus-Konto» verwalten')
            ->assertSee('Haushalte werden im «Narrenhaus-Konto» verwaltet. Hier kannst du sie nur ansehen.')
            ->assertSee('https://konto.example.com/households');
    }
}
