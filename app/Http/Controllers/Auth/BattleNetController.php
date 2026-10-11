<?php

namespace App\Http\Controllers\Auth;

use App\Actions\BattleNet\FetchBattleNetAccessToken;
use App\Contracts\Http\SharesBattleNetSession;
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
class BattleNetController extends Controller implements SharesBattleNetSession
{
    private const FAILED_MESSAGE = 'Failed to connect to Battle.net. Please try again.';

    /**
     * Redirect to Battle.net to authorise the `wow.profile` scope.
     */
    public function redirect(BlizzardConnector $blizzard): RedirectResponse
    {
        $state = Str::random(32);

        session()->put(self::STATE_KEY, $state);

        return redirect()->away($blizzard->getAuthorizationUrl($state));
    }

    /**
     * Handle the Battle.net callback, keep the access token in the session
     * until it expires, and return the user to the page that sent them.
     */
    public function callback(Request $request): RedirectResponse
    {
        $expectedState = session()->pull(self::STATE_KEY);
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
            $authenticator = FetchBattleNetAccessToken::run($code);
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

        session()->put(self::AUTHENTICATOR_KEY, $authenticator);

        return $this->returnTo()->with('success', 'Connected to Battle.net.');
    }

    private function failed(): RedirectResponse
    {
        return $this->returnTo()->with('error', self::FAILED_MESSAGE);
    }

    /**
     * Redirect to the URL the server stored before sending the user to
     * Battle.net, or to the account page.
     */
    private function returnTo(): RedirectResponse
    {
        return redirect()->to(session()->pull(self::INTENDED_URL_KEY) ?? route('account.index'));
    }
}
