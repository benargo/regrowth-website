import { CloseButton } from "@headlessui/react";
import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import { SITE_SECTIONS } from "@/Helpers/siteSections";
import { usePermissionCheck } from "@/Hooks/usePermission";
import cn from "@/Helpers/cn";

const FOCUS_RING = "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400";

function ItemIcon({ icon, className = "" }) {
    return (
        <span
            className={cn("flex h-4 w-4 flex-none items-center justify-center text-amber-400", className)}
            aria-hidden="true"
        >
            <Icon icon={icon} style="light" className="h-4 w-4" />
        </span>
    );
}

function TileItems({ items, classes }) {
    return (
        <ul
            className="grid grid-flow-col grid-cols-2 grid-rows-[repeat(var(--rows),auto)] gap-1"
            style={{ "--rows": Math.ceil(items.length / 2) }}
        >
            {items.map((item) => (
                <li key={item.key}>
                    <CloseButton
                        as={Link}
                        href={route(item.routeName)}
                        className={`flex h-full items-start gap-4 rounded-lg p-3 transition-colors hover:bg-white/5 ${FOCUS_RING}`}
                    >
                        <span
                            className={cn(
                                "flex h-10 w-10 flex-none items-center justify-center rounded-lg bg-amber-600/15 text-amber-400",
                                classes.flyoutTileIcon,
                            )}
                            aria-hidden="true"
                        >
                            <Icon icon={item.icon} style="light" className="h-5 w-5" />
                        </span>
                        <span className="flex flex-col gap-1">
                            <span className="text-sm font-semibold text-white">{item.label}</span>
                            <span className="text-sm text-gray-400">{item.description}</span>
                        </span>
                    </CloseButton>
                </li>
            ))}
        </ul>
    );
}

function BrowseItems({ items, classes }) {
    const [firstItem, ...otherItems] = items;

    return (
        <ul className="flex flex-col gap-1">
            {firstItem && (
                <li>
                    <CloseButton
                        as={Link}
                        href={route(firstItem.routeName)}
                        className={`-mx-2 flex items-start gap-3 rounded px-2 py-2 transition-colors hover:bg-white/5 ${FOCUS_RING}`}
                    >
                        <ItemIcon icon={firstItem.icon} className={cn("mt-0.5", classes.flyoutItemIcon)} />
                        <span className="flex flex-col gap-1">
                            <span className="text-sm font-semibold text-white">{firstItem.label}</span>
                            <span className="text-sm text-gray-400">{firstItem.description}</span>
                        </span>
                    </CloseButton>
                </li>
            )}
            {otherItems.map((item) => (
                <li key={item.key}>
                    <CloseButton
                        as={Link}
                        href={route(item.routeName)}
                        className={`-mx-2 flex items-center gap-3 rounded px-2 py-2 text-sm font-semibold text-white transition-colors hover:bg-white/5 ${FOCUS_RING}`}
                    >
                        <ItemIcon icon={item.icon} className={classes.flyoutItemIcon} />
                        {item.label}
                    </CloseButton>
                </li>
            ))}
        </ul>
    );
}

function ListItems({ items, classes }) {
    return (
        <ul className="flex flex-col">
            {items.map((item) => (
                <li key={item.key}>
                    <CloseButton
                        as={Link}
                        href={route(item.routeName)}
                        className={`flex items-center gap-2 rounded-md px-3 py-2 text-sm text-gray-300 hover:bg-white/5 hover:text-white ${FOCUS_RING}`}
                    >
                        <ItemIcon icon={item.icon} className={classes.flyoutItemIcon} />
                        {item.label}
                    </CloseButton>
                </li>
            ))}
        </ul>
    );
}

/**
 * The section's items the viewer may open, as tiles (icon, label and
 * description), a browse list (the first item described, the rest as links)
 * or a plain list of labels, each with its icon. `classes` overrides the icon colours.
 */
export default function FlyoutItems({ section, variant = "list", classes = {} }) {
    const hasPermission = usePermissionCheck();
    const items = SITE_SECTIONS[section].items.filter((item) => !item.permission || hasPermission(item.permission));

    return (
        <nav aria-label={`${SITE_SECTIONS[section].label} menu`}>
            {variant === "tiles" && <TileItems items={items} classes={classes} />}
            {variant === "browse" && <BrowseItems items={items} classes={classes} />}
            {variant === "list" && <ListItems items={items} classes={classes} />}
        </nav>
    );
}
