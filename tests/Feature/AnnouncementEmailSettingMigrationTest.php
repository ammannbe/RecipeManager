<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * The two announcement e-mail toggles (news, maintenance) become one setting (all | maintenance | none).
 */
class AnnouncementEmailSettingMigrationTest extends TestCase
{
    /**
     * Schema changes commit implicitly on MariaDB: no wrapping transaction, the rows are removed at the end.
     *
     * @return array<int, string|null>
     */
    protected function connectionsToTransact(): array
    {
        return [];
    }

    public function test_the_toggles_are_migrated_to_one_setting(): void
    {

        $all = User::factory()->create(['announcement_emails' => 'all']);
        $maintenance = User::factory()->create(['announcement_emails' => 'maintenance']);
        $none = User::factory()->create(['announcement_emails' => 'none']);
        $newsOnly = User::factory()->create();
        $ids = [$all->getKey(), $maintenance->getKey(), $none->getKey(), $newsOnly->getKey()];

        try {
            $this->migration('migrate:rollback');

            $toggles = fn (User $user): array => (array) DB::table('users')->where('id', $user->getKey())->first(['announcement_emails', 'maintenance_emails']);
            $this->assertEquals(['announcement_emails' => 1, 'maintenance_emails' => 1], $toggles($all));
            $this->assertEquals(['announcement_emails' => 0, 'maintenance_emails' => 1], $toggles($maintenance));
            $this->assertEquals(['announcement_emails' => 0, 'maintenance_emails' => 0], $toggles($none));
            DB::table('users')->where('id', $newsOnly->getKey())->update(['announcement_emails' => true, 'maintenance_emails' => false]);

            $this->migration('migrate');

            $this->assertSame('all', $all->refresh()->announcement_emails, 'news on → all');
            $this->assertSame('maintenance', $maintenance->refresh()->announcement_emails);
            $this->assertSame('none', $none->refresh()->announcement_emails, 'both off → none');
            $this->assertSame('all', $newsOnly->refresh()->announcement_emails, 'news on → all, also without maintenance');
        } finally {
            DB::table('users')->whereIn('id', $ids)->delete();
        }
    }

    /**
     * Runs only this migration down (migrate:rollback) or up again (migrate).
     */
    private function migration(string $command): void
    {
        $pending = $this->artisan($command, ['--path' => database_path('migrations/2026_11_18_090000_merge_announcement_email_settings.php'), '--realpath' => true, '--force' => true]);
        $this->assertInstanceOf(PendingCommand::class, $pending);
        $pending->assertSuccessful();
    }
}
