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
            $table->unsignedBigInteger('konto_version')->nullable()->after('konto_household_uuid');
            // Switching to the AccountManager: pending|rejected while a local household is proposed.
            $table->string('konto_import_status', 20)->nullable()->after('konto_version');
            // SHA-256 of the shares last reported to the AccountManager (only changes are sent).
            $table->string('konto_shares_hash', 64)->nullable()->after('konto_import_status');
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
        Schema::create('konto_webhook_events', function (Blueprint $table) {
            $table->uuid('event_id')->primary();
            $table->string('type', 100);
            $table->timestamp('created_at')->nullable()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('konto_webhook_events');
        Schema::dropIfExists('household_pending_members');

        Schema::table('households', function (Blueprint $table) {
            $table->dropColumn(['konto_version', 'konto_import_status', 'konto_shares_hash']);
        });
    }
};
