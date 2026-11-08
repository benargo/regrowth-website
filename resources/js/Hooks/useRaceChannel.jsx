import { useRef } from "react";
import { useEchoPublic } from "@laravel/echo-react";

/**
 * Subscribes to the public Race to Level 60 broadcast channel.
 *
 * @param {(payload: { checkedAt: string, hasChanges: boolean }) => void} onUpdated - handles .RaceStandingsUpdated
 */
export default function useRaceChannel(onUpdated) {
    const handlerRef = useRef(onUpdated);
    handlerRef.current = onUpdated;

    useEchoPublic("race-to-60", ".RaceStandingsUpdated", (payload) => handlerRef.current?.(payload), []);
}
