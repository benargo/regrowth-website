<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Discord\FetchGuildMember;
use App\Actions\Discord\RetrieveOAuthUser;
use App\Actions\Discord\SyncUserDiscordRoles;
use App\Http\Controllers\Controller;
use App\Http\Integrations\Discord\Exceptions\DiscordRequestException;
use App\Http\Integrations\Discord\Exceptions\RateLimitedException;
use App\Http\Integrations\Discord\Exceptions\UserNotInGuildException;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Saloon\Exceptions\Request\FatalRequestException;
use Spatie\LaravelData\Optional;

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
        } catch (\Exception) {
            return redirect('/')
                ->with('error', 'Failed to authenticate with Discord. Please try again.');
        }

        $rawData = $discordUser->getRaw();

        // Create or update user
        $user = User::updateOrCreate(
            ['id' => $discordUser->getId()],
            array_filter([
                'username' => $rawData['username'],
                'discriminator' => $rawData['discriminator'] ?? '0',
                'nickname' => $member->nick,
                'avatar' => $rawData['avatar'] ?? null,
                'guild_avatar' => $member->avatar,
                'banner' => is_string($member->banner) ? $member->banner : ($rawData['banner'] ?? null),
            ], fn (mixed $value): bool => ! $value instanceof Optional)
        );

        SyncUserDiscordRoles::run($user, $member->roles);

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
