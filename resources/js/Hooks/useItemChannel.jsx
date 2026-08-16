import { useRef } from "react";
import { useEchoPublic } from "@laravel/echo-react";

/**
 * Subscribes to the public broadcast channel for a loot item.
 *
 * @param {number|string} itemId
 * @param {(payload) => void} onUpdated - handles .ItemUpdated (notes / priorities)
 * @param {{
 *   onCommentPosted?: (payload) => void,
 *   onCommentChanged?: (payload) => void,
 *   onCommentRemoved?: (payload) => void,
 *   onCommentReactionChanged?: (payload) => void,
 * }} commentHandlers - optional; pages that render no comments may omit it
 */
export default function useItemChannel(itemId, onUpdated, commentHandlers = {}) {
    const handlerRef = useRef(onUpdated);
    handlerRef.current = onUpdated;

    const commentHandlersRef = useRef(commentHandlers);
    commentHandlersRef.current = commentHandlers;

    useEchoPublic(`item.${itemId}`, ".ItemUpdated", (payload) => handlerRef.current?.(payload), [itemId]);

    useEchoPublic(
        `item.${itemId}`,
        ".CommentPosted",
        (payload) => commentHandlersRef.current?.onCommentPosted?.(payload),
        [itemId],
    );

    useEchoPublic(
        `item.${itemId}`,
        ".CommentChanged",
        (payload) => commentHandlersRef.current?.onCommentChanged?.(payload),
        [itemId],
    );

    useEchoPublic(
        `item.${itemId}`,
        ".CommentRemoved",
        (payload) => commentHandlersRef.current?.onCommentRemoved?.(payload),
        [itemId],
    );

    useEchoPublic(
        `item.${itemId}`,
        ".CommentReactionChanged",
        (payload) => commentHandlersRef.current?.onCommentReactionChanged?.(payload),
        [itemId],
    );
}
