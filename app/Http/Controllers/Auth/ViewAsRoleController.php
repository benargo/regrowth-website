<?php

namespace App\Http\Controllers\Auth;

use App\Enums\DiscordRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use InvalidArgumentException;

class ViewAsRoleController extends Controller
{
    public const TEST_RAIDER_ID = '000000000000000000';

    public const TEST_MEMBER_ID = '000000000000000001';

    public const TEST_GUEST_ID = '000000000000000002';

    /**
     * Temporarily view the site as a different role.
     */
    public function viewAsRole(int $role, Request $request)
    {
        try {
            $currentUser = $request->user();

            $tempUser = $this->getTestUser($role);

            Auth::login($tempUser);

            $request->session()->regenerate();
            $request->session()->put('impersonating_user_id', $currentUser->id);

            return redirect('/')->with('success', 'You are now viewing the site as a '.DiscordRole::getRoleNameById($role).'.');
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

            return redirect()->route('dashboard.index')->with('success', 'You have returned to your original account.');
        } catch (ModelNotFoundException $e) {
            Auth::logout();

            return redirect('/')->with('error', 'There was an error returning to your original account.');
        }
    }

    /**
     * Get a test user for a specific role.
     */
    protected function getTestUser(string $role): User
    {
        return match ($role) {
            DiscordRole::Raider->value => User::firstOrCreate(
                ['id' => self::TEST_RAIDER_ID],
                [
                    'username' => 'testuser',
                    'discriminator' => '0000',
                    'nickname' => 'Test Raider',
                    'roles' => [DiscordRole::Member->value, DiscordRole::Raider->value],
                ]
            ),
            DiscordRole::Member->value => User::firstOrCreate(
                ['id' => self::TEST_MEMBER_ID],
                [
                    'username' => 'testuser',
                    'discriminator' => '0001',
                    'nickname' => 'Test Member',
                    'roles' => [DiscordRole::Member->value],
                ]
            ),
            DiscordRole::Guest->value => User::firstOrCreate(
                ['id' => self::TEST_GUEST_ID],
                [
                    'username' => 'testuser',
                    'discriminator' => '0002',
                    'nickname' => 'Test Guest',
                    'roles' => [DiscordRole::Guest->value],
                ]
            ),
            default => throw new \InvalidArgumentException('Invalid role specified.'),
        };
    }
}
