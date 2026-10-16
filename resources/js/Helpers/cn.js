import { clsx } from "clsx";
import { twMerge } from "tailwind-merge";

/**
 * Join class names (strings, arrays, or objects of class → condition) and
 * resolve conflicting Tailwind utilities so that later classes win.
 */
export default function cn(...inputs) {
    return twMerge(clsx(inputs));
}
