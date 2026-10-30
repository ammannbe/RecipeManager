<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foods, units, ingredient attributes and tags that non-admins add while importing a recipe
 * are requests: usable by the requester right away, hidden from others until an admin
 * approves them or merges them into an existing entry (requested_at set = pending).
 */
return new class extends Migration
{
    private const TABLES = ['foods', 'units', 'ingredient_attributes', 'tags'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                // No foreign key: a deleted requester must not approve or delete the entry.
                $table->unsignedBigInteger('requested_by')->nullable()->index();
                $table->timestamp('requested_at')->nullable()->index();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropIndex(['requested_by']);
                $table->dropIndex(['requested_at']);
                $table->dropColumn(['requested_by', 'requested_at']);
            });
        }
    }
};
