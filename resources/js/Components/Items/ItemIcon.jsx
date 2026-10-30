import { extractBreakpointProps } from "@/Helpers/TailwindBreakpoints";

export default function ItemIcon({
    itemId,
    itemName,
    itemQuality,
    iconUrl,
    size,
    wowheadUrl,
    domain = "tbc",
    ...props
}) {
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
            href={wowheadUrl || `https://www.wowhead.com/${domain}/item=${itemId}`}
            data-wowhead={`item=${itemId}&domain=${domain}`}
            target="_blank"
            rel="noopener noreferrer"
            tabIndex={-1}
        >
            <img
                src={iconUrl}
                alt={itemName}
                className={`${sizeClasses} box-shadow rounded border-2 ${itemQuality ?? "border-quality-common"}`}
            />
        </a>
    );
}
