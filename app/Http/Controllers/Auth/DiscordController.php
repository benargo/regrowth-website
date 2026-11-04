<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Discord\FetchGuildMember;
use App\Actions\Discord\RetrieveOAuthUser;
use App\Actions\Discord\UpsertUserFromGuildMember;
use App\Contracts\Actions\Discord\HoldsPendingGuildJoin;
use App\Http\Controllers\Controller;
use App\Http\Integrations\Discord\Exceptions\DiscordRequestException;
use App\Http\Integrations\Discord\Exceptions\RateLimitedException;
use App\Http\Integrations\Discord\Exceptions\UserNotInGuildException;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Saloon\Exceptions\Request\FatalRequestException;
use Symfony\Component\HttpFoundation\Response;

class DiscordController extends Controller implements HoldsPendingGuildJoin
{
    private const string RETURN_URL_SESSION_KEY = 'discord.return_url';

    /**
     * Remember the previous page and redirect to Discord OAuth, as a full page visit for Inertia requests.
     * Deletes any pending guild join first. A login that began at the Discord route also requests `guilds.join`.
     */
    public function redirect(): Response
    {
        session()->forget(self::SESSION_KEY);
        session()->put(self::RETURN_URL_SESSION_KEY, $this->previousPageUrl());

        $provider = Socialite::driver('discord')
            ->redirectUrl(route('discord.callback'))
            ->scopes(['identify', 'guilds.members.read']);

        if ($this->beganAtDiscordJoin()) {
            $provider->scopes(['guilds.join'])->withConsent();
        }

        return Inertia::location($provider->redirect()->getTargetUrl());
    }

    /**
     * Handle Discord OAuth callback. A non-member whose login began at the Discord route gets a
     * pending guild join, holding their encrypted access token, instead of a refusal.
     */
    public function callback(): RedirectResponse
    {
        try {
            $discordUser = RetrieveOAuthUser::run();
            $member = FetchGuildMember::run($discordUser->getId());
        } catch (UserNotInGuildException) {
            if ($this->beganAtDiscordJoin()) {
                session()->forget('url.intended');
                session()->put(self::SESSION_KEY, [
                    'user_id' => $discordUser->getId(),
                    'token' => Crypt::encryptString($discordUser->token),
                ]);
                session()->regenerate();

                return redirect()->route('discord');
            }

            return $this->refuseLogin('You must be a member of the Regrowth Discord server to log in.');
        } catch (DiscordRequestException|RateLimitedException|FatalRequestException $e) {
            report($e);

            return $this->refuseLogin('Failed to verify your Discord server membership. Please try again.');
        } catch (Exception) {
            return $this->refuseLogin('Failed to authenticate with Discord. Please try again.');
        }

        $user = UpsertUserFromGuildMember::run($member);

        if ($user === null) {
            return $this->refuseLogin('Failed to verify your Discord server membership. Please try again.');
        }

        Auth::login($user, remember: true);

        return redirect()->intended(session()->pull(self::RETURN_URL_SESSION_KEY, '/'));
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

    /**
     * Return home with the error, forgetting the Discord route as the intended URL.
     */
    private function refuseLogin(string $error): RedirectResponse
    {
        if ($this->beganAtDiscordJoin()) {
            session()->forget('url.intended');
        }

        return redirect('/')->with('error', $error);
    }

    /**
     * Whether this login began at the Discord route.
     */
    private function beganAtDiscordJoin(): bool
    {
        return session('url.intended') === route('discord');
    }

    /**
     * Resolve the Referer as a URL on this site, or the homepage when it is
     * missing, cross-origin, or part of the login flow.
     */
    private function previousPageUrl(): string
    {
        $originParts = ['scheme', 'host', 'port', 'user', 'pass'];
        $root = url('/');
        $referer = parse_url((string) request()->headers->get('referer')) ?: [];

        if (Arr::only($referer, $originParts) != Arr::only(parse_url($root), $originParts)) {
            return '/';
        }

        $path = $referer['path'] ?? '/';

        if (Str::is(['login', 'login/*', 'auth/*'], trim(rawurldecode($path), '/'))) {
            return '/';
        }

        $query = isset($referer['query']) ? "?{$referer['query']}" : '';

        return "{$root}{$path}{$query}";
    }
}
