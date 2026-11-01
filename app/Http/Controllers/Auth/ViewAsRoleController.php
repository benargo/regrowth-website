<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\DiscordRole;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ViewAsRoleController extends Controller
{
    /**
     * Temporarily view the site as a different role.
     */
    public function viewAsRole(string $role, Request $request)
    {
        try {
            $currentUser = $request->user();

            $discordRole = DiscordRole::visible()->find($role)
                ?? throw new InvalidArgumentException('Invalid role specified.');

            $tempUser = $this->getTestUser($discordRole);

            Auth::login($tempUser);

            $request->session()->regenerate();
            if (! $request->session()->has('impersonating_user_id')) {
                $request->session()->put('impersonating_user_id', $currentUser->id);
            }

            return redirect('/')->with('success', 'You are now viewing the site as a '.$discordRole->name.'.');
        } catch (InvalidArgumentException $e) {
            return redirect()->back()->with('error', 'Invalid role specified.');
        }
    }

    /**
     * Stop viewing the site as a different role.
     */
    public function stopViewingAs(Request $request)
    {
        try {
            $originalUserId = $request->session()->pull('impersonating_user_id');

            $originalUser = User::findOrFail($originalUserId);

            Auth::login($originalUser);

            $request->session()->regenerate();

            return redirect()->route('management.dashboard')->with('success', 'You have returned to your original account.');
        } catch (ModelNotFoundException $e) {
            Auth::logout();

            return redirect('/')->with('error', 'There was an error returning to your original account.');
        }
    }

    /**
     * Get the test user, holding only the given role.
     */
    protected function getTestUser(DiscordRole $discordRole): User
    {
        $userId = config('auth.local_users.view_as');

        if (blank($userId)) {
            throw new InvalidArgumentException('Invalid role specified.');
        }

        $user = User::firstOrCreate(
            ['id' => $userId],
            [
                'username' => 'testuser',
                'discriminator' => '0000',
                'nickname' => 'Test User',
            ]
        );

        $user->discordRoles()->sync([$discordRole->id]);

        return $user;
    }
}
