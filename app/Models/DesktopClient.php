<?php

namespace App\Models;

use App\Models\GuildRosterManager\Upload;
use App\Policies\DesktopClientPolicy;
use Database\Factories\DesktopClientFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\UsePolicy;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Laravel\Passport\Token;

/**
 * A Regrowth Desktop app installation linked to a user, identified by the ID the app sends.
 */
#[Fillable(['id', 'user_id', 'name', 'oauth_access_token_id', 'last_seen_at', 'last_upload_at'])]
#[Table(keyType: 'string', incrementing: false)]
#[UsePolicy(DesktopClientPolicy::class)]
class DesktopClient extends Model
{
    /** @use HasFactory<DesktopClientFactory> */
    use HasFactory;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'last_seen_at' => 'datetime',
            'last_upload_at' => 'datetime',
        ];
    }

    /**
     * Whether the access token and its refresh token are both unrevoked and the refresh token has not expired.
     * Reads the loaded `accessToken.refreshToken` relations.
     */
    protected function isLinked(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                $accessToken = $this->accessToken;
                $refreshToken = $accessToken?->refreshToken;

                return $accessToken !== null
                    && ! $accessToken->revoked
                    && $refreshToken !== null
                    && ! $refreshToken->revoked
                    && $refreshToken->expires_at?->isFuture() === true;
            },
        );
    }

    /**
     * Get the Passport access token issued to this client.
     *
     * @return BelongsTo<Token, $this>
     */
    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(Token::class, 'oauth_access_token_id');
    }

    /**
     * Get the access token bindings of this client, one per token in its refresh chain.
     *
     * @return HasMany<DesktopClientToken, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(DesktopClientToken::class);
    }

    /**
     * Get the guild roster uploads received from this client.
     *
     * @return HasMany<Upload, $this>
     */
    public function uploads(): HasMany
    {
        return $this->hasMany(Upload::class);
    }

    /**
     * Get the user this client is linked to.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Limit to clients with an unrevoked access token and an unrevoked, unexpired refresh token.
     *
     * @param  Builder<DesktopClient>  $query
     */
    #[Scope]
    protected function linked(Builder $query): void
    {
        $query->whereHas('accessToken', function (Builder $accessToken): void {
            $accessToken->where('revoked', false)
                ->whereHas('refreshToken', function (Builder $refreshToken): void {
                    $refreshToken->where('revoked', false)->where('expires_at', '>', now());
                });
        });
    }
}
