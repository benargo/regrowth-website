<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Discord\FetchGuildMember;
use App\Actions\Discord\RetrieveOAuthUser;
use App\Actions\Discord\UpsertUserFromGuildMember;
use App\Http\Controllers\Controller;
use App\Http\Integrations\Discord\Exceptions\DiscordRequestException;
use App\Http\Integrations\Discord\Exceptions\RateLimitedException;
use App\Http\Integrations\Discord\Exceptions\UserNotInGuildException;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Saloon\Exceptions\Request\FatalRequestException;

class DiscordController extends Controller
{
    /**
     * Redirect to Discord OAuth.
     */
    public function redirect(): RedirectResponse
    {
        return Socialite::driver('discord')
            ->scopes(['identify', 'guilds.members.read'])
            ->redirect();
    }

    /**
     * Handle Discord OAuth callback.
     */
    public function callback(): RedirectResponse
    {
        try {
            $discordUser = RetrieveOAuthUser::run();
            $member = FetchGuildMember::run($discordUser->getId());
        } catch (UserNotInGuildException) {
            return redirect('/')
                ->with('error', 'You must be a member of the Regrowth Discord server to log in.');
        } catch (DiscordRequestException|RateLimitedException|FatalRequestException $e) {
            report($e);

            return redirect('/')
                ->with('error', 'Failed to verify your Discord server membership. Please try again.');
        } catch (Exception) {
            return redirect('/')
                ->with('error', 'Failed to authenticate with Discord. Please try again.');
        }

        $user = UpsertUserFromGuildMember::run($member);

        Auth::login($user, remember: true);

        return redirect()->intended('/');
    }

    /**
     * Log the user out.
     */
    public function destroy(): RedirectResponse
    {
        Auth::logout();

        request()->session()->invalidate();
        request()->session()->regenerateToken();

        return redirect('/');
    }
}
