<?php

namespace App\Models;

use App\Mcp\McpPermission;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Laravel\Passport\AccessToken;

/**
 * Permissions a user gave an AI client (OAuth client registered through MCP).
 *
 * @property list<string> $permissions
 * @property Carbon|null $last_used_at
 */
class McpGrant extends Model
{
    protected $fillable = [
        'user_id',
        'oauth_client_id',
        'permissions',
        'last_used_at',
    ];

    protected $casts = [
        'permissions' => 'array',
        'last_used_at' => 'datetime',
    ];

    /**
     * The grant of the client behind the user's current access token, if any.
     */
    public static function current(?User $user): ?self
    {
        $token = $user?->token();

        if (! $token instanceof AccessToken || blank($token->oauth_client_id)) {
            return null;
        }

        return self::query()
            ->where('user_id', $user->getKey())
            ->where('oauth_client_id', $token->oauth_client_id)
            ->first();
    }

    public function allows(McpPermission $permission): bool
    {
        return in_array($permission->value, $this->permissions, true);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<OAuthClient, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(OAuthClient::class, 'oauth_client_id');
    }
}
