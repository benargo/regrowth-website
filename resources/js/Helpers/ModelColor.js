/**
 * Tailwind utilities that read a model's colour from the --model-color
 * variable. Tailwind v4 only generates classes it finds written out in full,
 * so these stay as literals and the colour itself arrives through the inline
 * style that modelColor() returns.
 */
const UTILITIES = {
    bg: "bg-(--model-color)",
    text: "text-(--model-color)",
    border: "border-(--model-color)",
    from: "from-(--model-color)",
    to: "to-(--model-color)",
    ring: "ring-(--model-color)",
};

/**
 * Turn a stored colour, such as "8b7ed0" or "#8b7ed0", into a CSS hex code.
 * Returns null when there is no colour.
 */
export function toHex(color) {
    return color ? `#${color.replace(/^#/, "")}` : null;
}

/**
 * The className and style that paint an element with a model's colour, for
 * any model with a hex `color` (game versions, raids). Spread both onto the
 * element; `utilities` picks which properties take the colour.
 *
 *     const { className, style } = modelColor(gameVersion.color, ["bg", "border"]);
 */
export default function modelColor(color, utilities = ["bg"]) {
    const hex = toHex(color);

    if (!hex) {
        return { className: "", style: undefined };
    }

    return {
        className: utilities.map((utility) => UTILITIES[utility]).join(" "),
        style: { "--model-color": hex },
    };
}
