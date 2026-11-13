<?php

namespace App\Http\Controllers;

use App\Http\Resources\DesktopClientResource;
use App\Models\DesktopClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Passport\Token;

class DesktopClientController extends Controller
{
    /**
     * Display every desktop client, most recently seen first.
     */
    #[Authorize('view-officer-dashboard')]
    public function index(): Response
    {
        return Inertia::render('Manage/DesktopClients/Index', [
            'devices' => DesktopClientResource::collection(
                DesktopClient::with(['user', 'accessToken.refreshToken'])->orderByDesc('last_seen_at')->get()
            ),
        ]);
    }

    /**
     * Sign a desktop client out by revoking every access token bound to it, its latest access token, and their
     * refresh tokens, leaving the desktop client row in place.
     */
    #[Authorize('delete', 'desktopClient')]
    public function destroy(DesktopClient $desktopClient): RedirectResponse
    {
        $this->revokeWithRefreshTokens(Token::where(function (Builder $query) use ($desktopClient): void {
            $query->whereIn('oauth_access_tokens.id', $desktopClient->tokens()->select('access_token_id'))
                ->orWhere('oauth_access_tokens.id', $desktopClient->oauth_access_token_id);
        }));

        return back()->with('success', "Signed out of {$desktopClient->name}.");
    }

    /**
     * Sign all of the authenticated user's desktop clients out by revoking every access and refresh token they hold
     * for the desktop OAuth client in a single query, leaving the desktop client rows in place.
     */
    public function destroyAllForUser(Request $request): RedirectResponse
    {
        $this->revokeWithRefreshTokens(
            Token::where('oauth_access_tokens.user_id', $request->user()->id)
                ->where('oauth_access_tokens.client_id', config('services.grm.desktop_oauth_client_id'))
        );

        return redirect()->route('account.index')->with('success', 'Signed out of all desktop apps.');
    }

    /**
     * Revoke the given access tokens and their refresh tokens in a single query.
     *
     * @param  Builder<Token>  $tokens
     */
    private function revokeWithRefreshTokens(Builder $tokens): void
    {
        $tokens->leftJoin('oauth_refresh_tokens', 'oauth_refresh_tokens.access_token_id', '=', 'oauth_access_tokens.id')
            ->update([
                'oauth_access_tokens.revoked' => true,
                'oauth_refresh_tokens.revoked' => true,
            ]);
    }
}
