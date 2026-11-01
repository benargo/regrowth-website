import cn from "@/Helpers/cn";
import modelColor from "@/Helpers/ModelColor";

const DEFAULT_ROLE_COLOR = "99aab5";

/**
 * A Discord role's name in its colour, after a dot of the same colour. Roles
 * without a colour use Discord's default grey. With `isDimmed`, the name is
 * grey and the dot faded. Long names truncate.
 */
export default function RoleName({ name, color, isDimmed = false, className = "" }) {
    const dotColor = modelColor(color ?? DEFAULT_ROLE_COLOR, ["bg"]);
    const textColor = modelColor(color ?? DEFAULT_ROLE_COLOR, ["text"]);

    return (
        <span className={cn("flex min-w-0 items-center gap-3", className)}>
            <span
                aria-hidden="true"
                className={cn("size-3 shrink-0 rounded-full", dotColor.className, isDimmed && "opacity-45")}
                style={dotColor.style}
            />
            <span
                className={cn("truncate font-medium", isDimmed ? "text-gray-500" : textColor.className)}
                style={isDimmed ? undefined : textColor.style}
            >
                {name}
            </span>
        </span>
    );
}
