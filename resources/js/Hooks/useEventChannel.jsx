import { useEffect, useRef } from "react";
import { useEcho } from "@laravel/echo-react";

/**
 * Subscribes to the private broadcast channel for a raid event and routes
 * incoming events to the provided handler callbacks.
 *
 * @param {number|string} eventId
 * @param {{
 *   onAssignmentChanged?: (payload) => void,
 *   onGroupChanged?: (payload) => void,
 *   onCompositionChanged?: (payload) => void,
 *   onBossStrategyChanged?: (payload) => void,
 * }} handlers
 * @param {string|null} draggingKey - _key of the assignment currently being dragged
 */
export default function useEventChannel(eventId, handlers = {}, draggingKey = null) {
    const handlersRef = useRef(handlers);
    handlersRef.current = handlers;

    const draggingKeyRef = useRef(draggingKey);
    draggingKeyRef.current = draggingKey;

    const pendingBuffer = useRef([]);

    // Flush buffered updates when dragging ends.
    const prevDraggingKey = useRef(draggingKey);
    useEffect(() => {
        if (prevDraggingKey.current !== null && draggingKey === null) {
            pendingBuffer.current.forEach(({ name, payload }) => dispatch(name, payload));
            pendingBuffer.current = [];
        }
        prevDraggingKey.current = draggingKey;
    }, [draggingKey]);

    function dispatch(name, payload) {
        const h = handlersRef.current;
        if (name === "AssignmentChanged" && h.onAssignmentChanged) h.onAssignmentChanged(payload);
        if (name === "GroupChanged" && h.onGroupChanged) h.onGroupChanged(payload);
        if (name === "CompositionChanged" && h.onCompositionChanged) h.onCompositionChanged(payload);
        if (name === "BossStrategyChanged" && h.onBossStrategyChanged) h.onBossStrategyChanged(payload);
    }

    function handleEvent(name, payload) {
        if (
            name === "AssignmentChanged" &&
            draggingKeyRef.current !== null &&
            payload.assignment?.id != null
        ) {
            pendingBuffer.current.push({ name, payload });
            return;
        }
        dispatch(name, payload);
    }

    // Assignment lifecycle (from EventAssignment model BroadcastsEvents trait)
    useEcho(
        `event.${eventId}`,
        ".EventAssignmentCreated",
        (p) => handleEvent("AssignmentChanged", { action: "created", assignment: p.assignment }),
        [eventId],
    );
    useEcho(
        `event.${eventId}`,
        ".EventAssignmentUpdated",
        (p) => handleEvent("AssignmentChanged", { action: "updated", assignment: p.assignment }),
        [eventId],
    );
    useEcho(
        `event.${eventId}`,
        ".EventAssignmentDeleted",
        (p) => handleEvent("AssignmentChanged", { action: "deleted", id: p.id }),
        [eventId],
    );

    // Group lifecycle (from EventAssignmentGroup model BroadcastsEvents trait)
    useEcho(
        `event.${eventId}`,
        ".EventGroupCreated",
        (p) => handleEvent("GroupChanged", { action: "created", group: p.group }),
        [eventId],
    );
    useEcho(
        `event.${eventId}`,
        ".EventGroupUpdated",
        (p) => handleEvent("GroupChanged", { action: "updated", group: p.group }),
        [eventId],
    );
    useEcho(
        `event.${eventId}`,
        ".EventGroupDeleted",
        (p) => handleEvent("GroupChanged", { action: "deleted", id: p.id }),
        [eventId],
    );

    // Controller-level broadcasts (reorder + composition sync)
    useEcho(`event.${eventId}`, ".AssignmentChanged", (p) => handleEvent("AssignmentChanged", p), [eventId]);
    useEcho(`event.${eventId}`, ".GroupChanged", (p) => handleEvent("GroupChanged", p), [eventId]);
    useEcho(`event.${eventId}`, ".CompositionChanged", (p) => handleEvent("CompositionChanged", p), [eventId]);
}

/**
 * Subscribe to boss strategy changes for one boss.
 *
 * @param {number|string} bossId
 * @param {(payload) => void} onBossStrategyChanged
 */
export function useBossStrategyChannel(bossId, onBossStrategyChanged) {
    const handlerRef = useRef(onBossStrategyChanged);
    handlerRef.current = onBossStrategyChanged;

    useEcho(
        `boss.${bossId}`,
        ".BossStrategyChanged",
        (payload) => handlerRef.current?.(payload),
        [bossId],
    );
}
