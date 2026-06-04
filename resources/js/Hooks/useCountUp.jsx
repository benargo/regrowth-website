import { useEffect, useRef, useState } from "react";

/**
 * Animates a numeric value towards a target using requestAnimationFrame.
 *
 * When the target changes mid-animation it re-targets from the currently
 * displayed value, so counters tick up smoothly between sparse broadcast
 * updates without snapping back.
 *
 * @param {number} target - the value to animate towards
 * @param {number} duration - animation duration in milliseconds
 * @returns {number} the current animated display value (rounded)
 */
export default function useCountUp(target, duration = 400) {
    const [display, setDisplay] = useState(target);
    const frameRef = useRef(null);
    const displayRef = useRef(target);

    displayRef.current = display;

    useEffect(() => {
        const from = displayRef.current;
        const to = target;

        if (from === to) {
            return;
        }

        const start = performance.now();

        const tick = (now) => {
            const elapsed = now - start;
            const progress = Math.min(elapsed / duration, 1);
            // easeOutCubic for a natural deceleration
            const eased = 1 - Math.pow(1 - progress, 3);
            const value = from + (to - from) * eased;

            setDisplay(progress < 1 ? value : to);

            if (progress < 1) {
                frameRef.current = requestAnimationFrame(tick);
            }
        };

        frameRef.current = requestAnimationFrame(tick);

        return () => {
            if (frameRef.current) {
                cancelAnimationFrame(frameRef.current);
            }
        };
    }, [target, duration]);

    return Math.round(display);
}
