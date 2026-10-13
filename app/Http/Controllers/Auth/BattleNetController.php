<?php

namespace App\Http\Controllers\Auth;

use App\Actions\BattleNet\FetchBattleNetAccessToken;
use App\Http\Controllers\Controller;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Support\BattleNetSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Log;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

/**
 * Connects the signed-in user's session to Battle.net via the Authorization Code Flow.
 */
#[Middleware('auth')]
class BattleNetController extends Controller
{
    private const FAILED_MESSAGE = 'Failed to connect to Battle.net. Please try again.';

    /**
     * Redirect to Battle.net to authorise the `wow.profile` scope.
     */
    public function redirect(BlizzardConnector $blizzard, BattleNetSession $battleNet): RedirectResponse
    {
        return redirect()->away($blizzard->getAuthorizationUrl($battleNet->startAuthorization()));
    }

    /**
     * Handle the Battle.net callback, keep the access token in the session
     * until it expires, and return the user to the page that sent them.
     */
    public function callback(Request $request, BattleNetSession $battleNet): RedirectResponse
    {
        $code = $request->query('code');

        $isValidCallback = $battleNet->verifyState($request->query('state'))
            && ! $request->filled('error')
            && is_string($code) && $code !== '';

        if (! $isValidCallback) {
            return $this->failed($battleNet);
        }

        try {
            $authenticator = FetchBattleNetAccessToken::run($code);
        } catch (RequestException $exception) {
            Log::warning('Battle.net authorization code exchange failed.', [
                'status' => $exception->getResponse()->status(),
                'error' => $exception->getResponse()->json('error'),
            ]);

            return $this->failed($battleNet);
        } catch (FatalRequestException $exception) {
            Log::warning('Battle.net authorization code exchange failed.', [
                'message' => $exception->getMessage(),
            ]);

            return $this->failed($battleNet);
        }

        $battleNet->store($authenticator);

        return $this->returnTo($battleNet)->with('success', 'Connected to Battle.net.');
    }

    private function failed(BattleNetSession $battleNet): RedirectResponse
    {
        return $this->returnTo($battleNet)->with('error', self::FAILED_MESSAGE);
    }

    /**
     * Redirect to the URL the server stored before sending the user to
     * Battle.net, or to the account page.
     */
    private function returnTo(BattleNetSession $battleNet): RedirectResponse
    {
        return redirect()->to($battleNet->pullIntendedUrl() ?? route('account.index'));
    }
}
