<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Last entitlements snapshot of the AccountManager per linked user (ENTITLEMENTS_DRIVER / AUTH_ADMIN_SOURCE).
        Schema::create('user_entitlements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            // active|requested|suspended|none
            $table->string('status', 20);
            $table->boolean('admin')->default(false);
            $table->json('features');
            $table->unsignedBigInteger('version')->default(0);
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_entitlements');
    }
};
