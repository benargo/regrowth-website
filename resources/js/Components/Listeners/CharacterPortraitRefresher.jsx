import { useEffect } from "react";
import { router } from "@inertiajs/react";
import useCharacterPortraitChannel from "@/Hooks/useCharacterPortraitChannel";

/**
 * Reload the page's optional `character_portrait` prop, which queues a fresh
 * portrait for the character if theirs is missing or stale.
 */
function reloadCharacterPortrait(characterId) {
    router.reload({
        only: ["character_portrait"],
        data: { character_portrait: characterId },
        preserveUrl: true,
    });
}

/**
 * Renders nothing; reloads the page's `character_portrait` prop for the
 * character with `characterId` on mount, and again once the portrait job
 * broadcasts.
 */
export default function CharacterPortraitRefresher({ characterId }) {
    useEffect(() => {
        reloadCharacterPortrait(characterId);
    }, [characterId]);

    useCharacterPortraitChannel(characterId, () => reloadCharacterPortrait(characterId));

    return null;
}
