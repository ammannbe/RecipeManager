<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class LocaleSwitchingTest extends TestCase
{
    public function test_the_configured_locales_have_translation_files(): void
    {
        foreach (array_keys(config('app.locales')) as $locale) {
            $this->assertFileExists(lang_path($locale.'.json'));
        }
    }

    public function test_every_locale_file_defines_the_same_keys(): void
    {
        $keys = [];

        foreach (array_keys(config('app.locales')) as $locale) {
            /** @var array<string, string> $translations */
            $translations = json_decode((string) file_get_contents(lang_path($locale.'.json')), true);

            $keys[$locale] = array_keys($translations);
            sort($keys[$locale]);
        }

        $this->assertSame(...array_values($keys));
    }

    public function test_a_guest_can_switch_the_language(): void
    {
        $this->get(route('profile.locale', 'en'))
            ->assertRedirect()
            ->assertSessionHas('locale', 'en');
    }

    public function test_an_unknown_locale_is_rejected(): void
    {
        $this->get(route('profile.locale', 'fr'))
            ->assertNotFound()
            ->assertSessionMissing('locale');
    }

    public function test_the_session_locale_is_applied_to_later_requests(): void
    {
        $this->withSession(['locale' => 'en'])
            ->get(route('recipes.index'))
            ->assertOk()
            ->assertSee('lang="en"', escape: false);

        $this->withSession(['locale' => 'de'])
            ->get(route('recipes.index'))
            ->assertOk()
            ->assertSee('lang="de"', escape: false);
    }

    public function test_the_choice_is_stored_on_the_user(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)->get(route('profile.locale', 'en'));

        $this->assertSame('en', $user->refresh()->locale);
    }

    /**
     * The stored choice must survive a new session on another device.
     */
    public function test_a_users_locale_wins_over_a_fresh_session(): void
    {
        $user = User::factory()->create(['locale' => 'en']);

        $this->actingAs($user)
            ->get(route('recipes.index'))
            ->assertOk()
            ->assertSee('lang="en"', escape: false);
    }

    public function test_the_switcher_is_rendered_on_the_public_pages(): void
    {
        $this->get(route('recipes.index'))
            ->assertOk()
            ->assertSee(route('profile.locale', 'en'), escape: false)
            ->assertSee(route('profile.locale', 'de'), escape: false);
    }

    public function test_the_switcher_is_rendered_in_the_admin_panel(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/admin')
            ->assertOk()
            ->assertSee(route('profile.locale', 'en'), escape: false);
    }

    public function test_translations_actually_change(): void
    {
        $this->withSession(['locale' => 'de'])
            ->get(route('recipes.index'))
            ->assertOk()
            ->assertSee('Rezepte');

        $this->withSession(['locale' => 'en'])
            ->get(route('recipes.index'))
            ->assertOk()
            ->assertSee('Recipes');
    }

    public function test_the_browser_preference_is_used_when_nothing_is_stored(): void
    {
        $this->withHeader('Accept-Language', 'en-GB,en;q=0.9')
            ->get(route('recipes.index'))
            ->assertOk()
            ->assertSee('lang="en"', escape: false);
    }
}
