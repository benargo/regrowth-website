import { useEffect } from "react";
import { Link, usePage } from "@inertiajs/react";
import { PopoverGroup } from "@headlessui/react";
import Dropdown from "@/Components/Dropdown";
import Icon from "@/Components/FontAwesome/Icon";
import NavLink from "@/Components/NavLink";
import Kbd from "@/Components/Kbd";
import Pill from "@/Components/Pill";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink";
import { Can } from "@/Components/Authorizable";
import GameVersionSwitcher, { GameVersionSwitchList } from "@/Components/GameVersions/GameVersionSwitcher";
import SectionDisclosure from "@/Components/Navigation/SectionDisclosure";
import SectionFlyout from "@/Components/Navigation/SectionFlyout";
import cn from "@/Helpers/cn";
import modelColor, { contrastingTextColor } from "@/Helpers/ModelColor";

const MOBILE_LINK =
    "flex w-full flex-row items-center rounded-md px-3 py-2 text-left text-sm hover:bg-white/5 hover:text-white active:bg-white/10";

const DESKTOP_BREAKPOINT_QUERY = "(min-width: 64rem)";

export const NAV_CLASSES = {
    classic: {
        wrapper: "bg-parchment",
        nav: "bg-brown-900/80 border-brown-700/50",
        iconButton: "hover:bg-brown-700 text-gray-400",
        searchButton: "hover:bg-brown-700 border-brown-600 bg-brown-800 focus:ring-amber-500 text-gray-300",
        searchShortcut: "bg-brown-900 text-gray-400",
        switcherButton: "hover:bg-brown-700 border-brown-600 bg-brown-800 focus:ring-amber-500 text-gray-300",
        switcherOptions: "bg-brown-800",
        switcherOption: "data-focus:bg-brown-700",
        mobileMenu: "bg-brown-900",
        flyoutPanel: "bg-brown-900/95 border-brown-700/50",
        mobileLink: "text-gray-300",
        mobileDivider: "border-amber-700",
        footer: "border-transparent",
        footerHeading: "text-amber-400",
        footerHeadingLink: "text-amber-400 hover:text-amber-300",
        footerText: "text-gray-400",
        footerLink: "text-gray-300 hover:text-white",
    },
    forever: {
        wrapper: "bg-parchment-forever",
        nav: "bg-forever-900/80 border-forever-700/50",
        iconButton: "hover:bg-forever-700 text-verdant-400",
        searchButton: "hover:bg-forever-700 border-forever-600 bg-forever-800 focus:ring-camel-500 text-verdant-300",
        searchShortcut: "bg-forever-900 text-verdant-300",
        switcherButton: "hover:bg-forever-700 border-forever-600 bg-forever-800 focus:ring-camel-500 text-verdant-300",
        switcherOptions: "bg-forever-800",
        switcherOption: "data-focus:bg-forever-700",
        navLink: "hover:bg-forever-700/80 focus-visible:outline-camel-400",
        responsiveNavLink: "text-verdant-300 focus-visible:outline-camel-400",
        dropdownContent: "py-1 bg-forever-800",
        dropdownLink: "bg-forever-800 data-focus:bg-forever-700",
        mobileMenu: "bg-forever-900",
        flyoutPanel: "bg-forever-900/95 border-forever-700/50",
        flyoutTileIcon: "bg-camel-600/15 text-camel-400",
        flyoutItemIcon: "text-camel-400",
        flyoutHeading: "text-camel-400",
        nextRaidCard: "border-camel-600 bg-camel-900/30",
        nextRaidHeading: "text-camel-300",
        nextRaidIcon: "bg-camel-600/20 text-camel-300",
        nextRaidSignup: "text-camel-200",
        nextRaidButton: "bg-camel-700 hover:bg-camel-800 focus-visible:outline-camel-300",
        mobileLink: "text-verdant-300",
        mobileDivider: "border-camel-700",
        footer: "bg-footer-forever border-camel-600",
        footerHeading: "text-camel-400",
        footerHeadingLink: "text-camel-400 hover:text-camel-200",
        footerText: "text-camel-400",
        footerLink: "text-camel-400 hover:text-camel-200",
    },
};

/**
 * The signed-in user, impersonation flag, game versions and active section
 * flags shared by the desktop and mobile navigation.
 */
export function useNavigationState() {
    const { auth, gameVersions: { data: gameVersions = [] } = {} } = usePage().props;

    return {
        user: auth?.user?.data,
        impersonating: auth?.impersonating,
        gameVersions,
        isRosterActive: route().current("roster.*") || route().current("characters.show"),
        isRaidingActive: route().current("raiding.*"),
        isLootActive: route().current("loot.*"),
        // isAddonsActive: route().current("addons.*"),
    };
}

/**
 * The button, shown below the desktop breakpoint, that opens and closes the
 * mobile menu.
 */
export function MobileMenuToggle({ classes, isOpen, onToggle, toggleRef }) {
    return (
        <button
            className={cn(
                "inline-flex size-10 items-center justify-center rounded-md hover:text-white focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-inset lg:hidden",
                classes.iconButton,
            )}
            type="button"
            ref={toggleRef}
            onClick={onToggle}
            aria-controls="mobile-menu"
            aria-expanded={isOpen}
            aria-label={isOpen ? "Close menu" : "Open menu"}
        >
            <span aria-hidden="true" className={isOpen ? "hidden" : "block"}>
                <Icon icon="bars" style="regular" className="h-6 w-6" />
            </span>
            <span aria-hidden="true" className={isOpen ? "block" : "hidden"}>
                <Icon icon="times" style="regular" className="h-6 w-6" />
            </span>
        </button>
    );
}

/**
 * The fixed main navigation bar: the logo, the section flyouts, search, the
 * game version switcher and the account menu, plus the given
 * `mobileMenuToggle` and a search button shown below the desktop breakpoint.
 * `navigationState` is the value returned by `useNavigationState()`.
 */
export function DesktopNavigation({ classes, navigationState, mobileMenuToggle, onSearch }) {
    const { user, impersonating, isRosterActive, isRaidingActive, isLootActive } = navigationState;
    const highestRoleColor = modelColor(user?.highest_role?.primary_color, ["bg"]);
    const isMac = typeof navigator !== "undefined" && /Mac|iPhone|iPad/.test(navigator.platform);

    return (
        <div className="fixed inset-x-0 top-4 z-30 px-4">
            <nav
                aria-label="Main"
                className={cn(
                    "relative grid grid-cols-[auto_1fr_auto] items-center overflow-visible rounded-lg border py-3 pr-3 pl-4 shadow-lg backdrop-blur-xl lg:flex lg:gap-6 lg:px-4 2xl:gap-10",
                    classes.nav,
                )}
            >
                {mobileMenuToggle}

                <Link
                    className="flex flex-row items-center justify-center p-1 font-serif text-lg text-white transition-opacity hover:opacity-80 lg:-my-2.5 lg:justify-start lg:p-0"
                    href="/"
                >
                    <img
                        src="/images/logo_regrowthforever.webp"
                        alt="Regrowth Forever logo"
                        width={1325}
                        height={1325}
                        className="mr-2 inline-block max-h-8 w-auto lg:max-h-14"
                    />
                    <span className="hidden lg:inline">Regrowth</span>
                </Link>

                {/* Mobile search button */}
                <button
                    className={cn(
                        "inline-flex items-center justify-center justify-self-end rounded-md p-2 hover:text-white focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-inset lg:hidden",
                        classes.iconButton,
                    )}
                    type="button"
                    onClick={onSearch}
                    aria-label="Search"
                >
                    <Icon icon="search" style="regular" className="h-6 w-6" />
                </button>

                {/* Desktop menu */}
                <div className="hidden lg:flex lg:flex-1 lg:items-center lg:justify-between">
                    <PopoverGroup className="flex items-center gap-1">
                        <NavLink className={classes.navLink} href={route("roster.index")} active={isRosterActive}>
                            <Icon icon="users" style="solid" className="mr-2 h-6" />
                            Roster
                        </NavLink>
                        <SectionFlyout section="raiding" active={isRaidingActive} classes={classes} />
                        <SectionFlyout section="loot" active={isLootActive} classes={classes} />
                        {/* <SectionFlyout section="addons" active={isAddonsActive} classes={classes} /> */}
                        <NavLink
                            className={classes.navLink}
                            href={route("discord")}
                            target="_blank"
                            rel="nofollow noopener noreferrer"
                            external
                        >
                            <Icon icon="discord" style="brands" className="mr-2 h-6" />
                            Discord
                        </NavLink>
                    </PopoverGroup>

                    <div className="flex items-center gap-2 2xl:gap-3">
                        <button
                            type="button"
                            onClick={onSearch}
                            aria-keyshortcuts="Meta+K Control+K"
                            className={cn(
                                "flex min-h-6 items-center gap-2 rounded border px-3 py-2 text-sm transition-colors hover:text-white focus:outline-hidden focus-visible:ring-1",
                                classes.searchButton,
                            )}
                        >
                            <Icon icon="search" style="solid" className="h-4 w-4" />
                            <span>Search</span>
                            <span
                                aria-hidden="true"
                                className={cn(
                                    "hidden rounded px-1.5 py-0.5 text-xs 2xl:inline",
                                    classes.searchShortcut,
                                )}
                            >
                                <Kbd>{isMac ? "⌘K" : "Ctrl K"}</Kbd>
                            </span>
                        </button>
                        {user && <GameVersionSwitcher classes={classes} />}
                        {user ? (
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button className="flex items-center space-x-2 rounded-md text-sm font-medium text-white transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400">
                                        <img src={user.avatar} alt="" className="h-8 w-8 rounded-full" />
                                        <span className="sr-only 2xl:hidden">{user.display_name}</span>
                                        <span className="hidden 2xl:inline">{user.display_name}</span>
                                        {user.highest_role && (
                                            <span className="hidden 2xl:inline-flex">
                                                <Pill
                                                    bgColor={highestRoleColor.className || undefined}
                                                    style={highestRoleColor.style}
                                                    textColor={contrastingTextColor(user.highest_role.primary_color)}
                                                >
                                                    {user.highest_role.name}
                                                </Pill>
                                            </span>
                                        )}
                                        <Icon icon="chevron-down" style="regular" className="h-6" />
                                    </button>
                                </Dropdown.Trigger>

                                <Dropdown.Content contentClasses={classes.dropdownContent}>
                                    <Dropdown.Link className={classes.dropdownLink} href={route("account.index")}>
                                        <Icon icon="user-cog" style="regular" className="mr-2 h-6" />
                                        Account Settings
                                    </Dropdown.Link>
                                    {impersonating && (
                                        <Dropdown.Link
                                            className={classes.dropdownLink}
                                            href={route("auth.return-to-self")}
                                        >
                                            <Icon icon="undo" style="regular" className="mr-2 h-6" />
                                            Return to my account
                                        </Dropdown.Link>
                                    )}
                                    <Can permission="view-officer-dashboard">
                                        <Dropdown.Link
                                            className={classes.dropdownLink}
                                            href={route("management.dashboard")}
                                        >
                                            <Icon icon="cogs" style="regular" className="mr-2" />
                                            Control Panel
                                        </Dropdown.Link>
                                    </Can>
                                    <Dropdown.Link
                                        className={classes.dropdownLink}
                                        href={route("logout")}
                                        method="post"
                                        as="button"
                                    >
                                        <Icon icon="sign-out" style="regular" className="mr-2 h-6" />
                                        Logout
                                    </Dropdown.Link>
                                </Dropdown.Content>
                            </Dropdown>
                        ) : (
                            <a
                                href={route("login")}
                                className="flex items-center space-x-2 rounded-md bg-[#5865F2] px-4 py-2 text-white transition-colors hover:bg-[#4752C4]"
                            >
                                <Icon icon="discord" style="brands" className="mr-2 h-6" />
                                <span className="2xl:hidden">Login</span>
                                <span className="hidden 2xl:inline">Login with Discord</span>
                            </a>
                        )}
                    </div>
                </div>
            </nav>
        </div>
    );
}

/**
 * The full-screen mobile menu with the section disclosures, game version
 * switcher and account links. While open it locks page
 * scrolling, closes on Escape (returning focus to `mobileMenuToggleRef`), and
 * closes once the viewport reaches the desktop breakpoint.
 * `navigationState` is the value returned by `useNavigationState()`.
 */
export function MobileNavigation({ classes, navigationState, isOpen, onClose, mobileMenuToggleRef }) {
    const { user, impersonating, gameVersions, isRosterActive, isRaidingActive, isLootActive } = navigationState;
    const highestRoleTextColor = modelColor(user?.highest_role?.primary_color, ["text"]);
    const mobileLinkClass = cn(MOBILE_LINK, classes.mobileLink);

    // Lock page scrolling while open, keeping any scrollbar's gutter in place.
    useEffect(() => {
        if (!isOpen) {
            return;
        }
        const root = document.documentElement;
        const { overflow: previousOverflow, scrollbarGutter: previousScrollbarGutter } = root.style;
        if (window.innerWidth > root.clientWidth) {
            root.style.scrollbarGutter = "stable";
        }
        root.style.overflow = "hidden";
        return () => {
            root.style.overflow = previousOverflow;
            root.style.scrollbarGutter = previousScrollbarGutter;
        };
    }, [isOpen]);

    // Escape closes the menu and returns focus to its toggle.
    useEffect(() => {
        if (!isOpen) {
            return;
        }
        const handler = (e) => {
            if (e.key === "Escape") {
                onClose();
                mobileMenuToggleRef.current?.focus();
            }
        };
        document.addEventListener("keydown", handler);
        return () => document.removeEventListener("keydown", handler);
    }, [isOpen, onClose, mobileMenuToggleRef]);

    // Close the menu once the viewport reaches the desktop breakpoint.
    useEffect(() => {
        if (!isOpen) {
            return;
        }
        const desktopQuery = window.matchMedia(DESKTOP_BREAKPOINT_QUERY);
        const handler = (e) => {
            if (e.matches) {
                onClose();
            }
        };
        desktopQuery.addEventListener("change", handler);
        return () => desktopQuery.removeEventListener("change", handler);
    }, [isOpen, onClose]);

    return (
        <div
            className={cn(
                "fixed inset-0 z-20 scroll-pt-24 overflow-y-auto overscroll-contain pt-24 pb-6 transition-[opacity,display] transition-discrete duration-150 motion-reduce:transition-none lg:hidden starting:opacity-0",
                isOpen ? "block" : "hidden",
                classes.mobileMenu,
            )}
            id="mobile-menu"
        >
            <div className="space-y-1 px-2 pt-2 pb-3">
                <SectionDisclosure section="raiding" active={isRaidingActive} classes={classes} />
                <SectionDisclosure section="loot" active={isLootActive} classes={classes} />
                {/* <SectionDisclosure section="addons" active={isAddonsActive} classes={classes} /> */}
                <ResponsiveNavLink
                    className={classes.responsiveNavLink}
                    href={route("roster.index")}
                    active={isRosterActive}
                >
                    <Icon icon="users" style="solid" className="mr-2 h-6" />
                    Roster
                </ResponsiveNavLink>
                <ResponsiveNavLink
                    className={classes.responsiveNavLink}
                    href={route("discord")}
                    target="_blank"
                    rel="nofollow noopener noreferrer"
                    external
                >
                    <Icon icon="discord" style="brands" className="mr-2 h-6" />
                    <span className="flex-1">Discord</span>
                    <Icon icon="external-link" style="regular" className="h-4" aria-hidden="true" />
                </ResponsiveNavLink>
            </div>

            {user && gameVersions.length > 1 && (
                <div className={cn("border-t px-2 py-3", classes.mobileDivider)}>
                    <GameVersionSwitchList linkClassName={mobileLinkClass} />
                </div>
            )}

            <div className={cn("border-t pt-4 pb-3", classes.mobileDivider)}>
                {user ? (
                    <div key="mobile-account-user" className="space-y-2 px-2">
                        <Link href={route("account.index")} className={cn(mobileLinkClass, "gap-3")}>
                            <img src={user.avatar} alt="" className="h-10 w-10 rounded-full" />
                            <span className="flex flex-1 flex-col">
                                <span className="text-base font-medium text-white">{user.display_name}</span>
                                {user.highest_role && (
                                    <span
                                        className={`text-sm ${highestRoleTextColor.className}`}
                                        style={highestRoleTextColor.style}
                                    >
                                        {user.highest_role.name}
                                    </span>
                                )}
                            </span>
                            <span className="sr-only">Account settings</span>
                            <Icon icon="chevron-right" style="regular" className="h-4" />
                        </Link>
                        {impersonating && (
                            <Link href={route("auth.return-to-self")} className={mobileLinkClass}>
                                <Icon icon="undo" style="regular" className="mr-2" />
                                Return to my account
                            </Link>
                        )}
                        <Can permission="view-officer-dashboard">
                            <Link href={route("management.dashboard")} className={mobileLinkClass}>
                                <Icon icon="cogs" style="regular" className="mr-2" />
                                Control Panel
                            </Link>
                        </Can>
                        <Link href={route("logout")} method="post" as="button" className={mobileLinkClass}>
                            <Icon icon="sign-out" style="regular" className="mr-2" />
                            Logout
                        </Link>
                    </div>
                ) : (
                    <div key="mobile-account-guest" className="px-4">
                        <Link
                            href={route("login")}
                            className="flex items-center justify-center space-x-2 rounded-md bg-[#5865F2] px-4 py-2 text-white transition-colors hover:bg-[#4752C4]"
                        >
                            <Icon icon="discord" style="brands" className="mr-2" />
                            <span>Login with Discord</span>
                        </Link>
                    </div>
                )}
            </div>
        </div>
    );
}
