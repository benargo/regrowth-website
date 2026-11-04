<?php

namespace App\Http\Controllers;

use App\Actions\Discord\AddGuildMember;
use App\Actions\Discord\FetchGuildMember;
use App\Actions\Discord\UpsertUserFromGuildMember;
use App\Contracts\Actions\Discord\HoldsPendingGuildJoin;
use App\Http\Integrations\Discord\Data\Guilds\GuildMemberData;
use App\Http\Integrations\Discord\Exceptions\ApiException;
use App\Http\Integrations\Discord\Exceptions\DiscordRequestException;
use App\Http\Integrations\Discord\Exceptions\RateLimitedException;
use App\Http\Integrations\Discord\Exceptions\UserNotInGuildException;
use App\Http\Middleware\PreventIndexingAndCaching;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Saloon\Exceptions\Request\FatalRequestException;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

#[Middleware(PreventIndexingAndCaching::class)]
class DiscordJoinController extends Controller implements HoldsPendingGuildJoin
{
    private const int MAX_GUILDS_REACHED = 30001;

    private const int USER_BANNED = 40007;

    private const int INVALID_OAUTH_TOKEN = 50025;

    /**
     * Show the consent page for a pending join, send guests to sign in, and
     * send members to their landing channel.
     */
    public function show(): InertiaResponse|Response
    {
        if (session()->has(self::SESSION_KEY)) {
            return Inertia::render('Discord/Join');
        }

        if (Auth::guest()) {
            return $this->redirectToSignIn();
        }

        try {
            $member = FetchGuildMember::run(Auth::id());
        } catch (UserNotInGuildException) {
            Auth::logout();

            session()->invalidate();
            session()->regenerateToken();

            return $this->redirectToSignIn();
        } catch (DiscordRequestException|RateLimitedException|FatalRequestException $exception) {
            report($exception);

            return response()->view('errors.503', status: Response::HTTP_SERVICE_UNAVAILABLE);
        }

        return Inertia::location($this->landingUrl($member));
    }

    /**
     * Add the user holding a pending join to the guild, log them in and send
     * them to their landing channel. The pending join is removed first, so it
     * is gone whatever the outcome.
     */
    public function store(): Response
    {
        $pending = session()->pull(self::SESSION_KEY);

        if ($pending === null) {
            return redirect()->route('discord');
        }

        try {
            $member = AddGuildMember::run($pending['user_id'], Crypt::decryptString($pending['token']));
            $user = UpsertUserFromGuildMember::run($member);
        } catch (ApiException $exception) {
            return $this->refusedJoin($exception);
        } catch (Exception $exception) {
            return $this->failedJoin($exception);
        }

        if ($user === null) {
            return $this->failedJoin();
        }

        Auth::login($user, remember: true);

        return Inertia::location($this->landingUrl($member));
    }

    /**
     * Forget the pending join and return home.
     */
    public function destroy(): RedirectResponse
    {
        session()->forget(self::SESSION_KEY);

        return redirect('/');
    }

    /**
     * Respond to Discord refusing the add.
     */
    private function refusedJoin(ApiException $exception): RedirectResponse
    {
        return match ($exception->discordCode) {
            self::USER_BANNED => redirect('/')->with('error', "You can't be added to the Regrowth Discord server."),
            self::MAX_GUILDS_REACHED => redirect('/')->with('error', "You're in too many Discord servers. Leave one and try again."),
            self::INVALID_OAUTH_TOKEN => $this->restartSignIn($exception),
            default => $this->failedJoin($exception),
        };
    }

    /**
     * Report the exception and return to the Discord route to sign in again.
     */
    private function restartSignIn(ApiException $exception): RedirectResponse
    {
        report($exception);

        return redirect()->route('discord');
    }

    /**
     * Report the exception, if any, and return home with the generic error.
     */
    private function failedJoin(?Throwable $exception = null): RedirectResponse
    {
        if ($exception !== null) {
            report($exception);
        }

        return redirect('/')->with('error', 'Failed to add you to the Regrowth Discord server. Please try again.');
    }

    /**
     * Redirect to Discord sign-in, returning to the Discord route afterwards.
     */
    private function redirectToSignIn(): RedirectResponse
    {
        return redirect()->setIntendedUrl(route('discord'))->route('login');
    }

    /**
     * The URL of the Chat channel for members with a chat role, otherwise the
     * Introductions channel. Falls back to the guild URL with a warning when
     * the channel isn't configured.
     */
    private function landingUrl(GuildMemberData $member): string
    {
        $hasChatRole = array_intersect($member->roles, config('guild.discord_chat_role_ids')) !== [];
        $channelName = $hasChatRole ? 'chat' : 'introductions';
        $channelId = config("services.discord.channels.{$channelName}");
        $guildUrl = 'https://discord.com/channels/'.config('services.discord.server_id');

        if (blank($channelId)) {
            Log::warning("The Discord {$channelName} channel is not configured.");

            return $guildUrl;
        }

        return "{$guildUrl}/{$channelId}";
    }
}
