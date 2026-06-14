import { useRef } from "react";
import { useEchoPublic } from "@laravel/echo-react";

export default function useCharacterPortraitChannel(characterId, onAttached) {
    const handlerRef = useRef(onAttached);
    handlerRef.current = onAttached;

    useEchoPublic(
        `character.${characterId}`,
        ".CharacterPortraitAttached",
        (payload) => handlerRef.current?.(payload),
        [characterId],
    );
}
