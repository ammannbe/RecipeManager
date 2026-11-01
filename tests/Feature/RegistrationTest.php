<?php

namespace Tests\Feature;

use App\Filament\Auth\Register;
use App\Models\Author;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        Notification::fake();
    }

    public function test_a_guest_can_register_and_gets_an_author(): void
    {
        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'New Cook',
                'email' => 'new.cook@example.com',
                'password' => 'secret-password-123',
                'passwordConfirmation' => 'secret-password-123',
            ])
            ->call('register')
            ->assertHasNoFormErrors();

        $user = User::query()->where('email', 'new.cook@example.com')->firstOrFail();

        $this->assertSame('New Cook', $user->author->name);
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_author_name_must_be_unique(): void
    {
        Author::factory()->create(['name' => 'Taken Name']);

        Livewire::test(Register::class)
            ->fillForm([
                'name' => 'Taken Name',
                'email' => 'other@example.com',
                'password' => 'secret-password-123',
                'passwordConfirmation' => 'secret-password-123',
            ])
            ->call('register')
            ->assertHasFormErrors(['name' => 'unique']);

        $this->assertDatabaseMissing('users', ['email' => 'other@example.com']);
    }
}
