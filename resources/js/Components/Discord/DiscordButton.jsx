import Icon from "@/Components/FontAwesome/Icon";
import cn from "@/Helpers/cn";

/**
 * A large Discord-branded button with the Discord icon, rendered as a link by
 * default or as the element given in `as`.
 */
export default function DiscordButton({ as: Component = "a", className = "", children, ...props }) {
    return (
        <Component
            className={cn(
                "bg-discord border-discord/30 inline-flex items-center gap-3 rounded border px-8 py-4 text-lg text-white shadow-lg transition-[filter,box-shadow] hover:shadow-xl hover:brightness-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400",
                className,
            )}
            {...props}
        >
            <Icon icon="discord" style="brands" className="h-6 w-6" />
            {children}
        </Component>
    );
}
