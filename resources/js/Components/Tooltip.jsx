import { useLayoutEffect, useRef, useState } from "react";
import { createPortal } from "react-dom";

const GAP = 8;
const VIEWPORT_MARGIN = 16;

const ARROW_CLASSES = {
    top: "left-1/2 top-full -translate-x-1/2 border-t-gray-900",
    bottom: "left-1/2 bottom-full -translate-x-1/2 border-b-gray-900",
    left: "left-full top-1/2 -translate-y-1/2 border-l-gray-900",
    right: "right-full top-1/2 -translate-y-1/2 border-r-gray-900",
};

const clamp = (value, min, max) => Math.min(Math.max(value, min), max);

/**
 * Computes the tooltip's fixed-position coordinates for a given side.
 *
 * The tooltip is centered on the trigger along the cross-axis (horizontally
 * for top/bottom, vertically for left/right), then clamped so it never
 * overflows the viewport on that axis. The primary axis (distance from the
 * trigger, offset by GAP) is intentionally left unclamped — if a tooltip
 * placed "top" doesn't fit above the trigger, it will render off-screen
 * upward rather than flip sides. Callers who need edge-of-viewport triggers
 * to always be visible should choose `position` accordingly.
 */
function computePosition(triggerRect, tooltipRect, position) {
    if (position === "top" || position === "bottom") {
        // Cross-axis: center horizontally on the trigger, clamped to viewport width.
        const left = clamp(
            triggerRect.left + triggerRect.width / 2 - tooltipRect.width / 2,
            VIEWPORT_MARGIN,
            window.innerWidth - tooltipRect.width - VIEWPORT_MARGIN,
        );
        // Primary axis: place above/below the trigger, unclamped.
        const top = position === "top" ? triggerRect.top - tooltipRect.height - GAP : triggerRect.bottom + GAP;

        return { top, left };
    }

    // Cross-axis: center vertically on the trigger, clamped to viewport height.
    const top = clamp(
        triggerRect.top + triggerRect.height / 2 - tooltipRect.height / 2,
        VIEWPORT_MARGIN,
        window.innerHeight - tooltipRect.height - VIEWPORT_MARGIN,
    );
    // Primary axis: place left/right of the trigger, unclamped.
    const left = position === "left" ? triggerRect.left - tooltipRect.width - GAP : triggerRect.right + GAP;

    return { top, left };
}

/**
 * Hover tooltip rendered into a portal, positioned relative to its trigger
 * and clamped to stay within the viewport.
 *
 * Positioning happens in two passes:
 *  1. Initial render: the tooltip is rendered off-screen (top/left of
 *     -9999) and hidden via `visibility` so it has real dimensions to
 *     measure without ever flashing at the wrong spot.
 *  2. `useLayoutEffect` measures the trigger and tooltip with
 *     `getBoundingClientRect()`, runs `computePosition()`, and stores the
 *     result in `coords` — synchronously, before paint, so there's no
 *     visible jump.
 *
 * `position` ("top" | "bottom" | "left" | "right") picks which side of the
 * trigger the tooltip is anchored to. The tooltip uses `position: fixed`
 * (viewport-relative, not document-relative), so `coords` don't need
 * adjusting for page scroll.
 */
export default function Tooltip({ children, body, position = "top", className = "", ...props }) {
    const triggerRef = useRef(null);
    const tooltipRef = useRef(null);
    const [isVisible, setIsVisible] = useState(false);
    const [coords, setCoords] = useState(null);

    useLayoutEffect(() => {
        if (!isVisible || !triggerRef.current || !tooltipRef.current) {
            return;
        }

        const triggerRect = triggerRef.current.getBoundingClientRect();
        const tooltipRect = tooltipRef.current.getBoundingClientRect();

        setCoords(computePosition(triggerRect, tooltipRect, position));
    }, [isVisible, position]);

    return (
        <div
            ref={triggerRef}
            className={`relative inline-block ${className}`}
            onMouseEnter={() => setIsVisible(true)}
            onMouseLeave={() => setIsVisible(false)}
            {...props}
        >
            {children}
            {isVisible &&
                createPortal(
                    <div
                        ref={tooltipRef}
                        style={{
                            position: "fixed",
                            top: coords?.top ?? -9999,
                            left: coords?.left ?? -9999,
                            visibility: coords ? "visible" : "hidden",
                        }}
                        className={`pointer-events-none z-30 max-w-xs rounded bg-gray-900 px-2 py-1 text-xs text-white ${body ? "" : "w-max"}`}
                    >
                        {body}
                        <div className={`absolute border-4 border-transparent ${ARROW_CLASSES[position]}`}></div>
                    </div>,
                    document.body,
                )}
        </div>
    );
}
