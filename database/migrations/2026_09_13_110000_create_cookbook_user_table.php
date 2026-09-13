<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cookbook_user', function (Blueprint $table) {
            $table->id();
            $table->foreignId('cookbook_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Separate flags rather than a bitmask so they stay queryable.
            $table->boolean('can_admin')->default(false);
            $table->boolean('can_read')->default(true);
            $table->boolean('can_create')->default(false);
            $table->boolean('can_update')->default(false);
            $table->boolean('can_delete')->default(false);

            $table->timestamps();

            $table->unique(['cookbook_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('cookbook_user');
    }
};
