<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What a user allowed an AI client (MCP, OAuth client) to do: chosen on the approval screen,
 * changeable under "Connected AI clients".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mcp_grants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignUuid('oauth_client_id')->constrained('oauth_clients')->cascadeOnDelete();
            $table->json('permissions');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'oauth_client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mcp_grants');
    }
};
