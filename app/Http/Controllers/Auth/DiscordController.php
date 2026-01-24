<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\Discord\DiscordGuildService;
use App\Services\Discord\Exceptions\UserNotInGuildException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class DiscordController extends Controller
{
    public function __construct(
        protected DiscordGuildService $guildService
    ) {}

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
            $discordUser = Socialite::driver('discord')->user();
        } catch (\Exception $e) {
            return redirect('/')
                ->with('error', 'Failed to authenticate with Discord. Please try again.');
        }

        $discordId = $discordUser->getId();

        // Fetch guild member data
        try {
            $guildMemberData = $this->guildService->getGuildMember($discordId);
        } catch (UserNotInGuildException $e) {
            return redirect('/')
                ->with('error', 'You must be a member of the Regrowth Discord server to log in.');
        } catch (\Exception $e) {
            return redirect('/')
                ->with('error', 'Failed to verify your Discord server membership. Please try again.');
        }

        $rawData = $discordUser->getRaw();

        // Create or update user
        $user = User::updateOrCreate(
            ['id' => $discordId],
            [
                'username' => $rawData['username'],
                'discriminator' => $rawData['discriminator'] ?? '0',
                'nickname' => $guildMemberData['nick'],
                'avatar' => $rawData['avatar'] ?? null,
                'guild_avatar' => $guildMemberData['avatar'] ?? null,
                'banner' => $guildMemberData['banner'] ?? $rawData['banner'] ?? null,
                'roles' => $guildMemberData['roles'] ?? '[]',
            ]
        );

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
