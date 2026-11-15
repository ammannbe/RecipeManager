<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One setting for announcement e-mails instead of two toggles (same model as Shoppinglist and Chorelist):
     * users.announcement_emails all | maintenance (default) | none. News on → all, both off → none, else maintenance.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('announcement_email_setting', 20)->default('maintenance')->after('maintenance_emails');
        });

        DB::table('users')->where('announcement_emails', true)->update(['announcement_email_setting' => 'all']);
        DB::table('users')->where('announcement_emails', false)->where('maintenance_emails', false)->update(['announcement_email_setting' => 'none']);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['announcement_emails', 'maintenance_emails']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('announcement_email_setting', 'announcement_emails');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('announcement_emails', 'announcement_email_setting');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('announcement_emails')->default(false)->after('last_login_at');
            $table->boolean('maintenance_emails')->default(true)->after('announcement_emails');
        });

        DB::table('users')->where('announcement_email_setting', 'all')->update(['announcement_emails' => true]);
        DB::table('users')->where('announcement_email_setting', 'none')->update(['maintenance_emails' => false]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('announcement_email_setting');
        });
    }
};
