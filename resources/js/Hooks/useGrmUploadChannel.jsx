import { useRef } from "react";
import { useEcho } from "@laravel/echo-react";
import { usePage } from "@inertiajs/react";

/**
 * Subscribes to the authenticated user's private channel and routes GRM upload
 * broadcasts to the provided handler callbacks.
 *
 * @param {{
 *   onStarted?: (payload) => void,
 *   onProgressed?: (payload) => void,
 *   onCompleted?: (payload) => void,
 *   onFailed?: (payload) => void,
 *   onRetrying?: (payload) => void,
 * }} handlers
 */
export default function useGrmUploadChannel(handlers = {}) {
    const handlersRef = useRef(handlers);
    handlersRef.current = handlers;

    const userId = usePage().props.auth?.user?.id;
    const channel = `App.Models.User.${userId}`;

    useEcho(channel, ".GrmUploadStarted", (p) => handlersRef.current.onStarted?.(p), [userId]);
    useEcho(channel, ".GrmUploadProgressed", (p) => handlersRef.current.onProgressed?.(p), [userId]);
    useEcho(channel, ".GrmUploadCompleted", (p) => handlersRef.current.onCompleted?.(p), [userId]);
    useEcho(channel, ".GrmUploadFailed", (p) => handlersRef.current.onFailed?.(p), [userId]);
    useEcho(channel, ".GrmUploadRetrying", (p) => handlersRef.current.onRetrying?.(p), [userId]);
}
