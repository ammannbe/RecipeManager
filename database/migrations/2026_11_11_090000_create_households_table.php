<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('households', function (Blueprint $table) {
            $table->id();
            $table->uuid()->unique();
            $table->string('name');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            // Set once the household is mirrored from the AccountManager (HOUSEHOLDS_DRIVER=konto).
            $table->uuid('konto_household_uuid')->nullable()->unique();
            $table->timestamps();
        });

        Schema::create('household_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->timestamp('joined_at')->nullable();

            $table->unique(['household_id', 'user_id']);
        });

        Schema::create('household_invitations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();
            $table->string('email')->index();
            // Only the hash is stored; the plaintext token lives in the invitation mail.
            $table->string('token_hash', 64)->unique();
            $table->enum('role', ['admin', 'member'])->default('member');
            $table->foreignId('invited_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamps();

            $table->unique(['household_id', 'email']);
        });

        Schema::create('cookbook_household', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cookbook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('household_id')->constrained()->cascadeOnDelete();

            // The same grants as cookbook_user, applied to every member of the household.
            $table->boolean('can_admin')->default(false);
            $table->boolean('can_read')->default(true);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);

            $table->timestamps();

            $table->unique(['cookbook_id', 'household_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookbook_household');
        Schema::dropIfExists('household_invitations');
        Schema::dropIfExists('household_user');
        Schema::dropIfExists('households');
    }
};
