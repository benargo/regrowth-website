import { Menu, MenuButton, MenuItem, MenuItems } from "@headlessui/react";
import { Link } from "@inertiajs/react";
import { Fragment } from "react";
import cn from "@/Helpers/cn";

/**
 * Menu with keyboard navigation, outside-click and Escape dismissal, built on
 * Headless UI's `Menu`.
 */
const Dropdown = ({ children }) => {
    return (
        <Menu as="div" className="relative">
            {children}
        </Menu>
    );
};

/**
 * The menu button; `children` must be a single element.
 */
const Trigger = ({ children }) => {
    return <MenuButton as={Fragment}>{children}</MenuButton>;
};

const Content = ({ align = "right", width = "48", contentClasses = "py-1 bg-brown-800", children }) => {
    let alignmentClasses = "origin-top";

    if (align === "left") {
        alignmentClasses = "ltr:origin-top-left rtl:origin-top-right inset-s-0";
    } else if (align === "right") {
        alignmentClasses = "ltr:origin-top-right rtl:origin-top-left inset-e-0";
    }

    let widthClasses = "";

    if (width === "48") {
        widthClasses = "w-48";
    }

    return (
        <MenuItems
            transition
            className={cn(
                "absolute z-50 mt-2 rounded-md shadow-lg focus:outline-hidden",
                "transition ease-out data-closed:scale-95 data-closed:opacity-0 data-enter:duration-200 data-leave:duration-75 data-leave:ease-in",
                alignmentClasses,
                widthClasses,
            )}
        >
            <div className={cn("ring-opacity-5 rounded-md ring-1 ring-black", contentClasses)}>{children}</div>
        </MenuItems>
    );
};

/**
 * A menu item rendered as an Inertia `Link`; closes the menu when activated.
 */
const DropdownLink = ({ className = "", children, ...props }) => {
    return (
        <MenuItem>
            <Link
                {...props}
                className={cn(
                    "bg-brown-800 data-focus:bg-brown-700 flex w-full flex-row items-center rounded-md px-4 py-2 text-start text-sm leading-5 text-white transition duration-150 ease-in-out focus:outline-hidden",
                    className,
                )}
            >
                {children}
            </Link>
        </MenuItem>
    );
};

Dropdown.Trigger = Trigger;
Dropdown.Content = Content;
Dropdown.Link = DropdownLink;

export default Dropdown;
