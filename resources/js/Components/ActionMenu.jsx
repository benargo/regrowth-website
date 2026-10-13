import { Menu, MenuButton, MenuItem, MenuItems } from "@headlessui/react";
import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";

const ITEM_CLASS_NAME =
    "flex w-full items-center gap-2.5 rounded px-3 py-1.5 text-left text-sm text-white data-focus:bg-ground-700";

function ItemIcon({ icon }) {
    return icon ? (
        <Icon icon={icon} style="regular" className="text-secondary-300 w-4 text-center" aria-hidden="true" />
    ) : null;
}

/**
 * An ellipsis button that opens a keyboard-accessible menu of row actions.
 * The menu is anchored and portalled, so scrolling containers don't clip it.
 * Fill it with ActionMenuLink and ActionMenuButton items.
 */
export function ActionMenu({ label, children }) {
    return (
        <Menu>
            <MenuButton
                aria-label={label}
                className="text-secondary-300 data-focus:outline-ink-400 rounded px-2 py-1 data-focus:outline-2 data-hover:text-white data-open:text-white"
            >
                <Icon icon="ellipsis-h" style="solid" className="h-4" aria-hidden="true" />
            </MenuButton>
            <MenuItems
                anchor="bottom end"
                className="bg-ground-800 border-ink-600 z-50 min-w-36 rounded-md border p-1 shadow-lg [--anchor-gap:--spacing(1)] focus:outline-none"
            >
                {children}
            </MenuItems>
        </Menu>
    );
}

/** A menu item that makes an Inertia visit to `href`. */
export function ActionMenuLink({ href, icon, children }) {
    return (
        <MenuItem as={Link} href={href} className={ITEM_CLASS_NAME}>
            <ItemIcon icon={icon} />
            {children}
        </MenuItem>
    );
}

/** A menu item that runs `onClick`. */
export function ActionMenuButton({ onClick, icon, children }) {
    return (
        <MenuItem as="button" type="button" onClick={onClick} className={ITEM_CLASS_NAME}>
            <ItemIcon icon={icon} />
            {children}
        </MenuItem>
    );
}
