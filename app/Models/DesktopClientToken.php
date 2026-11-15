<?php

namespace App\Models;

use Database\Factories\DesktopClientTokenFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Passport\Token;

/**
 * Binds a Passport access token to the desktop client it was issued to.
 */
#[Fillable(['access_token_id', 'desktop_client_id'])]
#[Table('desktop_client_tokens', key: 'access_token_id', keyType: 'string', incrementing: false)]
class DesktopClientToken extends Model
{
    /** @use HasFactory<DesktopClientTokenFactory> */
    use HasFactory;

    /**
     * Get the bound Passport access token.
     *
     * @return BelongsTo<Token, $this>
     */
    public function accessToken(): BelongsTo
    {
        return $this->belongsTo(Token::class, 'access_token_id');
    }

    /**
     * Get the desktop client the token is bound to.
     *
     * @return BelongsTo<DesktopClient, $this>
     */
    public function desktopClient(): BelongsTo
    {
        return $this->belongsTo(DesktopClient::class);
    }

    /**
     * Scope the query to the binding of the access token to the desktop client.
     *
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function boundTo(Builder $query, string $accessTokenId, string $desktopClientId): void
    {
        $query->whereKey($accessTokenId)->where('desktop_client_id', $desktopClientId);
    }
}
