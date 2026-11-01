<?php

namespace Tests\Feature;

use App\Filament\Auth\Register;
use App\Models\Author;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\PendingCommand;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Filament::setCurrentPanel('app');
        Notification::fake();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Testable<Register>
     */
    private function submit(array $data = [], int $afterSeconds = Register::MIN_SECONDS_TO_SUBMIT): Testable
    {
        $component = Livewire::test(Register::class)->fillForm([
            'name' => 'New Cook',
            'email' => 'new.cook@example.com',
            'password' => 'secret-password-123',
            'passwordConfirmation' => 'secret-password-123',
            ...$data,
        ]);

        $this->travel($afterSeconds)->seconds();

        return $component->call('register');
    }

    private function prune(): PendingCommand
    {
        $command = $this->artisan('users:prune-unverified');
        $this->assertInstanceOf(PendingCommand::class, $command);

        return $command;
    }

    public function test_a_guest_can_register_and_gets_an_author(): void
    {
        $this->submit()->assertHasNoFormErrors();

        $user = User::query()->where('email', 'new.cook@example.com')->firstOrFail();

        $this->assertSame('New Cook', $user->author->name);
        $this->assertAuthenticatedAs($user);
    }

    public function test_the_author_name_must_be_unique(): void
    {
        Author::factory()->create(['name' => 'Taken Name']);

        $this->submit(['name' => 'Taken Name'])
            ->assertHasFormErrors(['name' => 'unique']);

        $this->assertDatabaseMissing('users', ['email' => 'new.cook@example.com']);
    }

    public function test_a_failed_user_insert_leaves_no_author_behind(): void
    {
        User::creating(fn () => throw new RuntimeException('Insert failed'));

        try {
            $this->submit(['name' => 'Orphan Cook']);

            $this->fail('The registration should have failed.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Insert failed', $exception->getMessage());
        }

        $this->assertDatabaseMissing('authors', ['name' => 'Orphan Cook']);
    }

    public function test_a_filled_honeypot_rejects_the_registration(): void
    {
        $this->submit(['website' => 'https://spam.example.com'])
            ->assertHasErrors('data.email');

        $this->assertDatabaseMissing('users', ['email' => 'new.cook@example.com']);
        $this->assertDatabaseMissing('authors', ['name' => 'New Cook']);
    }

    public function test_a_registration_submitted_too_fast_is_rejected(): void
    {
        $this->submit(afterSeconds: 0)->assertHasErrors('data.email');

        $this->assertDatabaseMissing('users', ['email' => 'new.cook@example.com']);
    }

    public function test_unverified_users_are_pruned_with_their_unused_author(): void
    {
        $stale = User::factory()->create([
            'email_verified_at' => null,
            'created_at' => now()->subDays(8),
        ]);
        $staleAuthorId = $stale->author_id;

        $this->prune()->assertSuccessful();

        $this->assertDatabaseMissing('users', ['id' => $stale->id]);
        $this->assertDatabaseMissing('authors', ['id' => $staleAuthorId]);
    }

    public function test_pruning_keeps_verified_recent_and_admin_users(): void
    {
        $verified = User::factory()->create(['created_at' => now()->subDays(30)]);
        $recent = User::factory()->create(['email_verified_at' => null, 'created_at' => now()->subDays(2)]);
        $admin = User::factory()->admin()->create(['email_verified_at' => null, 'created_at' => now()->subDays(30)]);

        $this->prune()->assertSuccessful();

        $this->assertDatabaseHas('users', ['id' => $verified->id]);
        $this->assertDatabaseHas('users', ['id' => $recent->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }
}
