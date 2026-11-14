<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // "Later" on the banner offering to link the OIDC sign-in (AUTH_OIDC_NUDGE).
            $table->timestamp('oidc_nudge_dismissed_at')->nullable()->after('oidc_sub');
            // Reported to the AccountManager (ACCOUNT_MANAGER_REPORT_USERS).
            $table->timestamp('last_login_at')->nullable()->after('oidc_nudge_dismissed_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['oidc_nudge_dismissed_at', 'last_login_at']);
        });
    }
};
