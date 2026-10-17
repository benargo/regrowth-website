import { CustomPicker } from "react-color";
import { EditableInput, Hue, Saturation } from "react-color/lib/components/common";
import { isValidHex } from "react-color/lib/helpers/color";

/**
 * Inline styles for react-color's parts, coloured with the Tailwind colour
 * variables.
 */
const styles = {
    saturation: {
        color: { borderRadius: "0.25rem" },
        white: { borderRadius: "0.25rem" },
        black: { borderRadius: "0.25rem", boxShadow: "inset 0 0 0 1px var(--color-amber-600)" },
    },
    hex: {
        wrap: { position: "relative", flex: 1 },
        input: {
            width: "100%",
            borderRadius: "0.25rem",
            border: "1px solid var(--color-amber-600)",
            background: "var(--color-brown-800)",
            color: "var(--color-white)",
            padding: "0.5rem 0.75rem 0.5rem 3rem",
            fontFamily: "var(--font-mono)",
            fontSize: "0.875rem",
        },
        label: {
            position: "absolute",
            top: "50%",
            left: "0.75rem",
            transform: "translateY(-50%)",
            color: "var(--color-gray-300)",
            fontSize: "0.75rem",
            fontWeight: 600,
            textTransform: "uppercase",
        },
    },
};

/** The ring that marks the chosen point on the saturation square. */
function SaturationPointer() {
    return <div className="size-3.5 -translate-x-1/2 -translate-y-1/2 rounded-full border-2 border-white shadow ring-1 ring-black/40" />;
}

/** The thumb that marks the chosen hue on the slider. */
function HuePointer() {
    return <div className="mt-[-2px] h-4 w-2 -translate-x-1/2 rounded-sm border-2 border-white shadow ring-1 ring-black/40" />;
}

/**
 * A colour picker in the site's palette: a saturation square, a hue slider and
 * a hex field beside a swatch of the current colour. Takes the same `color`,
 * `onChange` and `onChangeComplete` props as react-color's own pickers.
 */
function ColorPicker({ hex, hsl, hsv, onChange }) {
    return (
        <div className="border-amber-600 bg-brown-900 flex w-64 flex-col gap-3 rounded-md border p-3 shadow-lg">
            <div className="relative h-40">
                <Saturation hsl={hsl} hsv={hsv} pointer={SaturationPointer} style={styles.saturation} onChange={onChange} />
            </div>

            <div className="relative h-3">
                <Hue hsl={hsl} pointer={HuePointer} radius="0.25rem" onChange={onChange} />
            </div>

            <div className="flex items-center gap-3">
                <div
                    className="border-amber-600 size-9 shrink-0 rounded border"
                    style={{ background: hex }}
                    aria-hidden="true"
                />
                <div className="[&_input:focus]:outline-amber-400 flex-1 [&_input:focus]:outline-2 [&_input:focus]:outline-offset-2">
                    <EditableInput
                        label="hex"
                        value={hex}
                        style={styles.hex}
                        onChange={(data, event) => isValidHex(data.hex) && onChange({ hex: data.hex, source: "hex" }, event)}
                    />
                </div>
            </div>
        </div>
    );
}

export default CustomPicker(ColorPicker);
