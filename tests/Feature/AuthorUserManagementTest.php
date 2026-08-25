<?php

namespace Tests\Feature;

use App\Filament\Resources\Authors\Pages\CreateAuthor;
use App\Filament\Resources\Authors\Pages\EditAuthor;
use App\Models\Author;
use App\Models\User;
use Livewire\Livewire;
use Tests\TestCase;

class AuthorUserManagementTest extends TestCase
{
    public function test_admin_can_create_author_without_user_account(): void
    {
        $this->actingAs(User::factory()->create(['admin' => true]));

        Livewire::test(CreateAuthor::class)
            ->fillForm([
                'name' => 'Author without account',
                'has_user_account' => false,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $author = Author::query()->where('name', 'Author without account')->firstOrFail();

        $this->assertNull($author->user);
    }

    public function test_admin_can_create_author_with_user_account(): void
    {
        $this->actingAs(User::factory()->create(['admin' => true]));

        Livewire::test(CreateAuthor::class)
            ->fillForm([
                'name' => 'Author with account',
                'has_user_account' => true,
                'user.email' => 'new-user@example.com',
                'user.password' => 'secret-password',
                'user.admin' => true,
            ])
            ->call('create')
            ->assertHasNoFormErrors();

        $author = Author::query()->where('name', 'Author with account')->firstOrFail();

        $this->assertNotNull($author->user);
        $this->assertSame('new-user@example.com', $author->user->email);
        $this->assertTrue((bool) $author->user->admin);
        $this->assertTrue(\Hash::check('secret-password', (string) $author->user->password));
    }

    public function test_admin_can_edit_another_users_email_and_admin_flag(): void
    {
        $this->actingAs(User::factory()->create(['admin' => true]));

        $author = Author::factory()->create();
        $user = User::factory()->create(['author_id' => $author->id, 'admin' => false]);

        Livewire::test(EditAuthor::class, ['record' => $author->getKey()])
            ->fillForm([
                'name' => 'Renamed author',
                'has_user_account' => true,
                'user.email' => 'changed@example.com',
                'user.admin' => true,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $user->refresh();

        $this->assertSame('changed@example.com', $user->email);
        $this->assertTrue((bool) $user->admin);
        $this->assertSame('Renamed author', $author->refresh()->name);
    }

    public function test_editing_without_password_keeps_the_existing_one(): void
    {
        $this->actingAs(User::factory()->create(['admin' => true]));

        $author = Author::factory()->create();
        $user = User::factory()->create(['author_id' => $author->id]);
        $originalPassword = $user->password;

        Livewire::test(EditAuthor::class, ['record' => $author->getKey()])
            ->fillForm([
                'name' => $author->name,
                'has_user_account' => true,
                'user.email' => $user->email,
                'user.password' => '',
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame($originalPassword, $user->refresh()->password);
    }

    public function test_admin_can_remove_the_user_account_of_an_author(): void
    {
        $this->actingAs(User::factory()->create(['admin' => true]));

        $author = Author::factory()->create();
        $user = User::factory()->create(['author_id' => $author->id]);

        Livewire::test(EditAuthor::class, ['record' => $author->getKey()])
            ->fillForm([
                'name' => $author->name,
                'has_user_account' => false,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSoftDeleted($user);
        $this->assertNull($author->refresh()->user);
    }

    public function test_non_admin_cannot_access_the_author_resource(): void
    {
        $this->actingAs(User::factory()->create(['admin' => false]));

        $this->get('/admin/authors')->assertForbidden();
        $this->get('/admin/authors/create')->assertForbidden();
    }
}
