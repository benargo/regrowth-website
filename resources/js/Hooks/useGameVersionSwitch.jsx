import { useEffect, useSyncExternalStore } from "react";
import { router, usePage } from "@inertiajs/react";

export const GAME_VERSION_SWITCH_ERROR_BAG = "gameVersionSwitch";

const switchState = { isSwitching: false, queryResetCount: 0 };
const listeners = new Set();

/**
 * Set whether a game version switch is in flight and notify every subscriber.
 */
function setIsSwitching(isSwitching) {
    switchState.isSwitching = isSwitching;
    listeners.forEach((listener) => listener());
}

/**
 * Subscribe to changes of the shared switching state.
 */
function subscribe(listener) {
    listeners.add(listener);

    return () => listeners.delete(listener);
}

/**
 * Drop the page's query string when the game version is switched, while the
 * calling component is mounted.
 */
export function useResetQueryOnGameVersionSwitch() {
    useEffect(() => {
        switchState.queryResetCount += 1;

        return () => {
            switchState.queryResetCount -= 1;
        };
    }, []);
}

/**
 * Switch the site-wide current game version and expose the `current` one.
 * `isSwitching` is shared by every caller, and `switchTo(slug)` does nothing
 * for the current version or while a switch is in flight.
 */
export default function useGameVersionSwitch() {
    const { gameVersions = [] } = usePage().props;
    const isSwitching = useSyncExternalStore(subscribe, () => switchState.isSwitching);
    const current = gameVersions.find((gameVersion) => gameVersion.meta?.is_current);

    const switchTo = (slug) => {
        if (switchState.isSwitching || current?.slug === slug) {
            return;
        }

        router.patch(
            route("session.game-version.update"),
            { game_version: slug, reset_query: switchState.queryResetCount > 0 || undefined },
            {
                preserveScroll: true,
                errorBag: GAME_VERSION_SWITCH_ERROR_BAG,
                onStart: () => setIsSwitching(true),
                onFinish: () => setIsSwitching(false),
            },
        );
    };

    return { current, switchTo, isSwitching };
}
