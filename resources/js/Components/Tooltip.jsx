import { cloneElement, Fragment, isValidElement, useEffect, useId, useLayoutEffect, useRef, useState } from "react";
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
 * Returns the tooltip's fixed-position `{ top, left }` for the given side of
 * the trigger: offset from the trigger by GAP, and centered on it along the
 * other axis, clamped to the viewport. The tooltip does not flip sides.
 */
function computePosition(triggerRect, tooltipRect, position) {
    if (position === "top" || position === "bottom") {
        const left = clamp(
            triggerRect.left + triggerRect.width / 2 - tooltipRect.width / 2,
            VIEWPORT_MARGIN,
            window.innerWidth - tooltipRect.width - VIEWPORT_MARGIN,
        );
        const top = position === "top" ? triggerRect.top - tooltipRect.height - GAP : triggerRect.bottom + GAP;

        return { top, left };
    }

    const top = clamp(
        triggerRect.top + triggerRect.height / 2 - tooltipRect.height / 2,
        VIEWPORT_MARGIN,
        window.innerHeight - tooltipRect.height - VIEWPORT_MARGIN,
    );
    const left = position === "left" ? triggerRect.left - tooltipRect.width - GAP : triggerRect.right + GAP;

    return { top, left };
}

/**
 * Tooltip rendered into a portal with `position: fixed`, placed beside its
 * trigger and kept within the viewport. It is first rendered hidden off-screen,
 * then measured and positioned before paint.
 *
 * Shows on hover and focus; hides on mouse leave, blur and Escape. While
 * visible it renders with `role="tooltip"` and is referenced by
 * `aria-describedby` on `children` when that is a single non-Fragment element,
 * otherwise on the wrapper.
 *
 * Props:
 *  - `body`: the tooltip content.
 *  - `position`: "top" | "bottom" | "left" | "right" side of the trigger.
 *  - `as`: the wrapper element, e.g. "span" for inline content.
 *  - `describe={false}`: omits `aria-describedby`.
 *  - `openOnTap`: touch and pen taps toggle the tooltip, a tap outside hides
 *    it, and hover handlers are skipped for that input.
 */
export default function Tooltip({
    children,
    body,
    position = "top",
    className = "",
    as: Element = "div",
    describe = true,
    openOnTap = false,
    onClick,
    onPointerDown,
    onPointerEnter,
    onMouseEnter,
    onMouseLeave,
    onFocus,
    onBlur,
    ...props
}) {
    const triggerRef = useRef(null);
    const tooltipRef = useRef(null);
    const tooltipId = useId();
    const lastPointerTypeRef = useRef(null);
    const [isVisible, setIsVisible] = useState(false);
    const [coords, setCoords] = useState(null);

    useLayoutEffect(() => {
        if (!isVisible) {
            setCoords(null);
            return;
        }

        if (!triggerRef.current || !tooltipRef.current) {
            return;
        }

        const triggerRect = triggerRef.current.getBoundingClientRect();
        const tooltipRect = tooltipRef.current.getBoundingClientRect();

        setCoords(computePosition(triggerRect, tooltipRect, position));
    }, [isVisible, position]);

    useEffect(() => {
        if (!isVisible) {
            return;
        }

        const handleKeyDown = (event) => {
            if (event.key === "Escape") {
                setIsVisible(false);
            }
        };

        document.addEventListener("keydown", handleKeyDown);

        return () => document.removeEventListener("keydown", handleKeyDown);
    }, [isVisible]);

    useEffect(() => {
        if (!openOnTap || !isVisible) {
            return;
        }

        const handlePointerDown = (event) => {
            if (!triggerRef.current?.contains(event.target)) {
                setIsVisible(false);
            }
        };

        document.addEventListener("pointerdown", handlePointerDown);

        return () => document.removeEventListener("pointerdown", handlePointerDown);
    }, [openOnTap, isVisible]);

    const isTapInput = () => openOnTap && lastPointerTypeRef.current !== null && lastPointerTypeRef.current !== "mouse";

    const describedBy = describe && isVisible ? tooltipId : undefined;
    const isSingleElement = isValidElement(children) && children.type !== Fragment;
    const trigger = isSingleElement
        ? cloneElement(children, {
              "aria-describedby":
                  [children.props["aria-describedby"], describedBy].filter(Boolean).join(" ") || undefined,
          })
        : children;

    return (
        <Element
            ref={triggerRef}
            className={`relative inline-block ${className}`}
            aria-describedby={isSingleElement ? undefined : describedBy}
            onPointerEnter={(event) => {
                lastPointerTypeRef.current = event.pointerType;
                onPointerEnter?.(event);
            }}
            onPointerDown={(event) => {
                lastPointerTypeRef.current = event.pointerType;
                onPointerDown?.(event);
            }}
            onClick={(event) => {
                if (isTapInput()) {
                    setIsVisible((visible) => !visible);
                }
                onClick?.(event);
            }}
            onMouseEnter={(event) => {
                if (!isTapInput()) {
                    setIsVisible(true);
                }
                onMouseEnter?.(event);
            }}
            onMouseLeave={(event) => {
                if (!isTapInput()) {
                    setIsVisible(false);
                }
                onMouseLeave?.(event);
            }}
            onFocus={(event) => {
                setIsVisible(true);
                onFocus?.(event);
            }}
            onBlur={(event) => {
                if (!event.currentTarget.contains(event.relatedTarget)) {
                    setIsVisible(false);
                }
                onBlur?.(event);
            }}
            {...props}
        >
            {trigger}
            {isVisible &&
                createPortal(
                    <div
                        ref={tooltipRef}
                        id={tooltipId}
                        role="tooltip"
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
        </Element>
    );
}
