import cn from "@/Helpers/cn";

/**
 * The race medals by their `medal` value: label, finishing position, flat
 * colour and disc gradient.
 */
export const MEDALS = {
    gold: { label: "Gold", position: 1, color: "#E3B341", gradient: "linear-gradient(#ffe48a, #c9971c)" },
    silver: { label: "Silver", position: 2, color: "#C3CBD0", gradient: "linear-gradient(#f1f1f1, #9aa3a8)" },
    bronze: { label: "Bronze", position: 3, color: "#D08A4F", gradient: "linear-gradient(#f0b27a, #9a5a26)" },
};

const SIZE_CLASSES = {
    sm: "size-5 text-xs",
    md: "size-7 text-sm",
};

/**
 * A medal disc showing its finishing position, labelled with the medal's
 * name for assistive technology.
 */
export default function Medal({ medal, size = "md", className = "" }) {
    const details = MEDALS[medal];

    return (
        <span
            role="img"
            aria-label={details.label}
            className={cn(
                "inline-grid shrink-0 place-items-center rounded-full font-serif leading-none text-[#1a1208] shadow-[0_1px_3px_rgba(0,0,0,0.5)]",
                SIZE_CLASSES[size] ?? SIZE_CLASSES.md,
                className,
            )}
            style={{ backgroundImage: details.gradient }}
        >
            {details.position}
        </span>
    );
}
