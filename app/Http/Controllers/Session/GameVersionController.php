<?php

namespace App\Http\Controllers\Session;

use App\Actions\GameVersion\RememberGameVersion;
use App\Contracts\Http\Middleware\SharesGameVersionSwitchFlash;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateSessionGameVersionRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Routing\Attributes\Controllers\Middleware;

#[Middleware('throttle:60,1')]
class GameVersionController extends Controller implements SharesGameVersionSwitchFlash
{
    /**
     * Store the requested game version in the session, flag the switch as
     * explicit for the next request, and redirect back, without the previous page's query string when `reset_query` is set.
     */
    public function update(UpdateSessionGameVersionRequest $request): RedirectResponse
    {
        RememberGameVersion::run($request->validated('game_version'));
        session()->flash(self::SWITCHED_FLASH_KEY, true);

        if ($request->boolean('reset_query')) {
            return redirect()->to(strtok(url()->previous(), '?'), 303);
        }

        return back(303);
    }
}
