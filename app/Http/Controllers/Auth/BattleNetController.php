<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

/**
 * Connects the signed-in user's session to Battle.net via the Authorization Code Flow.
 */
#[Middleware('auth')]
class BattleNetController extends Controller
{
    private const STATE_SESSION_KEY = 'battlenet.oauth_state';

    private const FAILED_MESSAGE = 'Failed to connect to Battle.net. Please try again.';

    public function __construct(protected BlizzardConnector $blizzard) {}

    /**
     * Redirect to Battle.net to authorise the `wow.profile` scope.
     */
    public function redirect(): RedirectResponse
    {
        $state = Str::random(32);

        session()->put(self::STATE_SESSION_KEY, $state);

        return redirect()->away($this->blizzard->getAuthorizationUrl($state));
    }

    /**
     * Handle the Battle.net callback and keep the access token in the session
     * until it expires.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expectedState = session()->pull(self::STATE_SESSION_KEY);
        $state = $request->query('state');
        $code = $request->query('code');

        $isValidCallback = ! $request->filled('error')
            && is_string($expectedState) && $expectedState !== ''
            && is_string($state) && hash_equals($expectedState, $state)
            && is_string($code) && $code !== '';

        if (! $isValidCallback) {
            return $this->failed();
        }

        try {
            $authenticator = $this->blizzard->getUserAccessToken($code);
        } catch (RequestException $exception) {
            Log::warning('Battle.net authorization code exchange failed.', [
                'status' => $exception->getResponse()->status(),
                'error' => $exception->getResponse()->json('error'),
            ]);

            return $this->failed();
        } catch (FatalRequestException $exception) {
            Log::warning('Battle.net authorization code exchange failed.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->failed();
        }

        session()->put([
            'battlenet.access_token' => $authenticator->getAccessToken(),
            'battlenet.token_expires_at' => $authenticator->getExpiresAt()?->getTimestamp(),
        ]);

        return redirect()->route('account.index')
            ->with('success', 'Connected to Battle.net.');
    }

    private function failed(): RedirectResponse
    {
        return redirect()->route('account.index')->with('error', self::FAILED_MESSAGE);
    }
}
