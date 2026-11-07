<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('households', function (Blueprint $table) {
            // Version of the last applied snapshot of the AccountManager (older ones are ignored).
            $table->unsignedBigInteger('account_manager_version')->nullable()->after('account_manager_household_uuid');
            // Switching to the AccountManager: pending|rejected while a local household is proposed.
            $table->string('account_manager_import_status', 20)->nullable()->after('account_manager_version');
            // SHA-256 of the shares last reported to the AccountManager (only changes are sent).
            $table->string('account_manager_shares_hash', 64)->nullable()->after('account_manager_import_status');
        });

        // Members of mirrored households who have not used the app yet, attached on their first OIDC login.
        Schema::create('household_pending_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('oidc_sub');
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->timestamps();

            $table->unique(['household_id', 'oidc_sub']);
            $table->index('oidc_sub');
        });

        // Processed webhook events of the AccountManager (idempotency), pruned after 7 days.
        Schema::create('account_manager_webhook_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('type', 100);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('account_manager_webhook_events');
        Schema::dropIfExists('household_pending_members');

        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn(['account_manager_version', 'account_manager_import_status', 'account_manager_shares_hash']);
        });
    }
};
