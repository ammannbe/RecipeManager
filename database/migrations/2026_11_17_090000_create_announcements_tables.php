<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Local copy of the AccountManager's announcements (ANNOUNCEMENTS_DRIVER=account-manager).
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->uuid('account_manager_uuid')->unique();
            $table->string('type', 20);
            // Per locale (de, en), null when empty.
            $table->json('title');
            $table->json('body_html');
            $table->json('excerpt');
            $table->json('channels');
            $table->string('link', 2048)->nullable();
            $table->string('image_url', 2048)->nullable();
            $table->timestamp('publish_at')->index();
            $table->timestamp('expires_at')->nullable();
            // updated_at of the AccountManager: an unchanged copy is not written again.
            $table->timestamp('remote_updated_at')->nullable();
            // No longer visible for this service ("deleted" of the API or missing in a full sync). The copy stays
            // hidden instead of being deleted, so it is never sent twice when it comes back.
            $table->timestamp('withdrawn_at')->nullable();
            // Push and e-mail are dispatched once per announcement (claimed atomically).
            $table->timestamp('push_dispatched_at')->nullable();
            $table->timestamp('email_dispatched_at')->nullable();
            $table->timestamps();
        });

        Schema::create('announcement_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->timestamp('read_at')->nullable();
            $table->timestamp('dismissed_at')->nullable();
            $table->unique(['announcement_id', 'user_id']);
        });

        // One row per announcement, user and channel (push, email): never sent twice, also when jobs overlap or retry.
        Schema::create('announcement_deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('channel', 10);
            $table->string('status', 10)->default('pending');
            $table->timestamp('created_at')->nullable();
            $table->unique(['announcement_id', 'user_id', 'channel']);
        });

        Schema::table('users', function (Blueprint $table) {
            // Announcements by e-mail: news (feature/info) only with consent, maintenance until unsubscribed.
            $table->boolean('announcement_emails')->default(false)->after('last_login_at');
            $table->boolean('maintenance_emails')->default(true)->after('announcement_emails');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['announcement_emails', 'maintenance_emails']);
        });

        Schema::dropIfExists('announcement_deliveries');
        Schema::dropIfExists('announcement_reads');
        Schema::dropIfExists('announcements');
    }
};
