import useCharacterPortraitChannel from "@/Hooks/useCharacterPortraitChannel";

/**
 * Renders nothing; calls `onAttached` when a new portrait is attached to the
 * character with `characterId`.
 */
export default function CharacterPortraitListener({ characterId, onAttached }) {
    useCharacterPortraitChannel(characterId, onAttached);

    return null;
}
