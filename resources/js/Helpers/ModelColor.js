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

/** The WCAG relative luminance of a six-digit hex colour, such as "#8b7ed0". */
function relativeLuminance(hex) {
    const [red, green, blue] = [1, 3, 5].map((start) => {
        const channel = parseInt(hex.slice(start, start + 2), 16) / 255;

        return channel <= 0.04045 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * red + 0.7152 * green + 0.0722 * blue;
}

/** The luminance of Tailwind's gray-900 (#101828), the dark text colour. */
const DARK_TEXT_LUMINANCE = relativeLuminance("#101828");

/**
 * The text colour utility, "text-gray-900" or "text-white", with the higher
 * contrast against a background of the given model colour. Returns
 * "text-white" when there is no colour.
 */
export function contrastingTextColor(color) {
    const hex = toHex(color);

    if (!hex || hex.length !== 7) {
        return "text-white";
    }

    const luminance = relativeLuminance(hex);
    const contrastWithWhite = 1.05 / (luminance + 0.05);
    const contrastWithDark = (luminance + 0.05) / (DARK_TEXT_LUMINANCE + 0.05);

    return contrastWithDark > contrastWithWhite ? "text-gray-900" : "text-white";
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

/**
 * The className and style for a background that shows a model's image over
 * its colour, so the colour fills in while the image loads or when there is
 * none. Spread both onto the element alongside `bg-cover bg-center`. A CSS
 * `position`, such as "center bottom", overrides `bg-center` for the image.
 * The className is `fallbackClass` when there is neither image nor colour.
 *
 *     const { className, style } = modelBackground(raid.color, raid.banner_url, raid.banner_position, "bg-masthead");
 */
export function modelBackground(color, image, position = null, fallbackClass = "") {
    const tint = modelColor(color);

    return {
        className: image || color ? tint.className : fallbackClass,
        style: image
            ? { ...tint.style, backgroundImage: `url("${image}")`, ...(position && { backgroundPosition: position }) }
            : tint.style,
    };
}
