import { useRef } from "react";
import { useEchoPublic } from "@laravel/echo-react";

export default function useItemChannel(itemId, onUpdated) {
    const handlerRef = useRef(onUpdated);
    handlerRef.current = onUpdated;

    useEchoPublic(
        `item.${itemId}`,
        ".ItemUpdated",
        (payload) => handlerRef.current?.(payload),
        [itemId],
    );
}
