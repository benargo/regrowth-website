import Icon from "@/Components/FontAwesome/Icon";
import cn from "@/Helpers/cn";

/**
 * An icon-only button whose accessible name comes from the required `label`.
 * `iconClassName` is applied to the icon itself.
 */
export default function IconButton({ icon, label, iconStyle = "solid", iconClassName, className = "", ...props }) {
    if (process.env.NODE_ENV !== "production" && !label) {
        throw new Error("IconButton: a 'label' prop is required.");
    }

    return (
        <button
            type="button"
            aria-label={label}
            className={cn(
                "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400",
                className,
            )}
            {...props}
        >
            <Icon icon={icon} style={iconStyle} className={iconClassName} aria-hidden="true" />
        </button>
    );
}
