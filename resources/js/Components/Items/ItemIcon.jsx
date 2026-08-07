import { extractBreakpointProps } from "@/Helpers/TailwindBreakpoints";

export default function ItemIcon({ itemId, itemName, iconUrl, size, itemQuality, ...props }) {
    const sizeClasses = [
        `h-${size} w-${size}`,
        ...extractBreakpointProps(props)
            .filter(([, propName]) => propName === "size")
            .map(
                ([breakpoint, , breakpointSize]) =>
                    `${breakpoint}:h-${breakpointSize} ${breakpoint}:w-${breakpointSize}`,
            ),
    ].join(" ");

    return (
        <a
            href={`https://www.wowhead.com/tbc/item=${itemId}`}
            data-wowhead={`item=${itemId}&domain=tbc`}
            target="_blank"
            rel="noopener noreferrer"
            tabIndex={-1}
        >
            <img
                src={iconUrl}
                alt={itemName}
                className={`${sizeClasses} rounded border-2 ${itemQuality ?? "border-quality-common"}`}
            />
        </a>
    );
}
