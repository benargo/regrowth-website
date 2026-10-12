<?php

namespace App\Http\Controllers\Account;

use App\Attributes\UsesTheme;
use App\Enums\Theme;
use App\Http\Controllers\Controller;
use App\Models\PlannedAbsence;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

#[UsesTheme(Theme::FOREVER)]
class AccountController extends Controller
{
    /**
     * Display the authenticated user's account page.
     */
    public function __invoke(Request $request): Response
    {
        $user = $request->user()->load(['discordRoles', 'plannedAbsences.character']);

        return Inertia::render('Account/Index', [
            'roles' => $user->discordRoles
                ->where('is_visible', true)
                ->sortByDesc('position')
                ->values()
                ->map(fn ($role) => ['id' => $role->id, 'name' => $role->name]),
            'planned_absences' => $user->plannedAbsences
                ->toResourceCollection()
                ->additional(['permissions' => [
                    'edit' => $this->abilityByAbsence($user, 'update'),
                    'delete' => $this->abilityByAbsence($user, 'delete'),
                ]]),
        ]);
    }

    /**
     * Whether the user has the given ability on each of their absences,
     * keyed by absence ID.
     *
     * @return array<string, bool>
     */
    private function abilityByAbsence(User $user, string $ability): array
    {
        return $user->plannedAbsences
            ->mapWithKeys(fn (PlannedAbsence $absence): array => [$absence->id => $user->can($ability, $absence)])
            ->all();
    }
}
