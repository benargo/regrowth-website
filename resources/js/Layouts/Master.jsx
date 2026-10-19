import { useState, useEffect, useRef } from "react";
import { Link, Head, usePage } from "@inertiajs/react";
import { PopoverGroup } from "@headlessui/react";
import Dropdown from "@/Components/Dropdown";
import FlashMessage from "@/Components/FlashMessage";
import Icon from "@/Components/FontAwesome/Icon";
import NavLink from "@/Components/NavLink";
import Kbd from "@/Components/Kbd";
import Pill from "@/Components/Pill";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink";
import { Can } from "@/Components/Authorizable";
import GameVersionSwitcher, { GameVersionSwitchList } from "@/Components/GameVersions/GameVersionSwitcher";
import { GAME_VERSION_SWITCH_ERROR_BAG } from "@/Hooks/useGameVersionSwitch";
import SearchPalette from "@/Components/Search/SearchPalette";
import SectionDisclosure from "@/Components/Navigation/SectionDisclosure";
import SectionFlyout from "@/Components/Navigation/SectionFlyout";
import Footer from "@/Layouts/Footer";
import cn from "@/Helpers/cn";

const MOBILE_LINK = "flex w-full flex-row items-center rounded-md px-3 py-2 text-left text-sm hover:text-white";

const NAV_CLASSES = {
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
        mobileLink: "hover:bg-amber-700 text-gray-300",
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
        responsiveNavLink: "text-verdant-300 hover:bg-camel-800 focus-visible:outline-camel-400",
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
        mobileLink: "hover:bg-camel-800 text-verdant-300",
        mobileDivider: "border-camel-700",
        footer: "bg-footer-forever border-camel-600",
        footerHeading: "text-camel-400",
        footerHeadingLink: "text-camel-400 hover:text-camel-200",
        footerText: "text-camel-400",
        footerLink: "text-camel-400 hover:text-camel-200",
    },
};

export default function Master({ title, isHomepage = false, children }) {
    const { auth, flash, errors, discordInviteUrl, gameVersions: { data: gameVersions = [] } = {} } = usePage().props;
    const classes = isHomepage ? NAV_CLASSES.forever : NAV_CLASSES.classic;
    const mobileLinkClass = cn(MOBILE_LINK, classes.mobileLink);
    const isRosterActive = route().current("roster.*") || route().current("characters.show");
    const isRaidingActive = route().current("raiding.*");
    const isLootActive = route().current("loot.*");
    const user = auth?.user;
    const impersonating = auth?.impersonating;

    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const mobileMenuToggleRef = useRef(null);
    const [flashError, setFlashError] = useState(flash?.error);
    const [flashSuccess, setFlashSuccess] = useState(flash?.success);
    const [searchOpen, setSearchOpen] = useState(false);
    const isMac = typeof navigator !== "undefined" && /Mac|iPhone|iPad/.test(navigator.platform);

    // Update flash messages when props change
    useEffect(() => {
        setFlashError(flash?.error);
        setFlashSuccess(flash?.success);
    }, [flash?.error, flash?.success]);

    // Surface game version switch validation errors as a flash error.
    const gameVersionSwitchError = errors?.[GAME_VERSION_SWITCH_ERROR_BAG]?.game_version;
    useEffect(() => {
        if (gameVersionSwitchError) {
            setFlashError(gameVersionSwitchError);
        }
    }, [gameVersionSwitchError]);

    // Ctrl/Cmd+K opens the search palette from anywhere in the app.
    useEffect(() => {
        const handler = (e) => {
            if ((e.metaKey || e.ctrlKey) && e.key.toLowerCase() === "k") {
                e.preventDefault();
                setSearchOpen(true);
            }
        };
        document.addEventListener("keydown", handler);
        return () => document.removeEventListener("keydown", handler);
    }, []);

    // Lock page scrolling while the full-screen mobile menu is open, keeping any scrollbar's gutter in place.
    useEffect(() => {
        if (!showingNavigationDropdown) {
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
    }, [showingNavigationDropdown]);

    // Escape closes the mobile menu and returns focus to its toggle.
    useEffect(() => {
        if (!showingNavigationDropdown) {
            return;
        }
        const handler = (e) => {
            if (e.key === "Escape") {
                setShowingNavigationDropdown(false);
                mobileMenuToggleRef.current?.focus();
            }
        };
        document.addEventListener("keydown", handler);
        return () => document.removeEventListener("keydown", handler);
    }, [showingNavigationDropdown]);

    // Close the mobile menu once the viewport reaches the desktop breakpoint.
    useEffect(() => {
        if (!showingNavigationDropdown) {
            return;
        }
        const desktopQuery = window.matchMedia("(min-width: 64rem)");
        const handler = (e) => {
            if (e.matches) {
                setShowingNavigationDropdown(false);
            }
        };
        desktopQuery.addEventListener("change", handler);
        return () => desktopQuery.removeEventListener("change", handler);
    }, [showingNavigationDropdown]);

    return (
        <>
            <Head title={title} />

            <div className={cn("flex min-h-screen flex-col text-white", classes.wrapper)}>
                <a
                    href="#main-content"
                    className="bg-brown-900 fixed top-2 left-2 z-50 -translate-y-[200%] rounded px-4 py-2 focus-visible:translate-y-0 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                >
                    Skip to main content
                </a>
                <div className="fixed inset-x-0 top-4 z-30 px-4">
                    <nav
                        aria-label="Main"
                        className={cn(
                            "relative grid grid-cols-[auto_1fr_auto] items-center overflow-visible rounded-lg border py-3 pr-3 pl-4 shadow-lg backdrop-blur-xl lg:flex lg:flex-wrap lg:gap-10 lg:px-4",
                            classes.nav,
                        )}
                    >
                        {/* Mobile menu toggle */}
                        <button
                            className={cn(
                                "inline-flex size-10 items-center justify-center rounded-md hover:text-white focus:outline-hidden focus-visible:ring-2 focus-visible:ring-white focus-visible:ring-inset lg:hidden",
                                classes.iconButton,
                            )}
                            type="button"
                            ref={mobileMenuToggleRef}
                            onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                            aria-controls="mobile-menu"
                            aria-expanded={showingNavigationDropdown}
                            aria-label={showingNavigationDropdown ? "Close menu" : "Open menu"}
                        >
                            <span aria-hidden="true" className={showingNavigationDropdown ? "hidden" : "block"}>
                                <Icon icon="bars" style="regular" className="h-6 w-6" />
                            </span>
                            <span aria-hidden="true" className={showingNavigationDropdown ? "block" : "hidden"}>
                                <Icon icon="times" style="regular" className="h-6 w-6" />
                            </span>
                        </button>

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
                            onClick={() => setSearchOpen(true)}
                            aria-label="Search"
                        >
                            <Icon icon="search" style="regular" className="h-6 w-6" />
                        </button>

                        {/* Desktop menu */}
                        <div className="hidden lg:flex lg:flex-1 lg:items-center lg:justify-between">
                            <PopoverGroup className="flex items-center gap-1">
                                <NavLink
                                    className={classes.navLink}
                                    href={route("roster.index")}
                                    active={isRosterActive}
                                >
                                    <Icon icon="users" style="solid" className="mr-2 h-6" />
                                    Roster
                                </NavLink>
                                <SectionFlyout section="raiding" active={isRaidingActive} classes={classes} />
                                <SectionFlyout section="loot" active={isLootActive} classes={classes} />
                                <NavLink
                                    className={classes.navLink}
                                    href={discordInviteUrl}
                                    external
                                    rel="noopener noreferrer"
                                >
                                    <Icon icon="discord" style="brands" className="mr-2 h-6" />
                                    Discord
                                </NavLink>
                            </PopoverGroup>

                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={() => setSearchOpen(true)}
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
                                        className={cn("rounded px-1.5 py-0.5 text-xs", classes.searchShortcut)}
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
                                                <span>{user.display_name}</span>
                                                {user.highest_role && (
                                                    <Pill
                                                        bgColor={`bg-discord-${user.highest_role ? user.highest_role.toLowerCase() : "grey-800"}`}
                                                    >
                                                        {user.highest_role}
                                                    </Pill>
                                                )}
                                                <Icon icon="chevron-down" style="regular" className="h-6" />
                                            </button>
                                        </Dropdown.Trigger>

                                        <Dropdown.Content contentClasses={classes.dropdownContent}>
                                            <Dropdown.Link
                                                className={classes.dropdownLink}
                                                href={route("account.index")}
                                            >
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
                                                    <div className="flex flex-col items-start gap-1">
                                                        <span>Control Panel</span>
                                                    </div>
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
                                        <span>Login with Discord</span>
                                    </a>
                                )}
                            </div>
                        </div>
                    </nav>
                </div>

                {/* Mobile menu */}
                <div
                    className={cn(
                        "fixed inset-0 z-20 scroll-pt-24 overflow-y-auto overscroll-contain pt-24 pb-6 transition-[opacity,display] transition-discrete duration-150 motion-reduce:transition-none lg:hidden starting:opacity-0",
                        showingNavigationDropdown ? "block" : "hidden",
                        classes.mobileMenu,
                    )}
                    id="mobile-menu"
                >
                    <div className="space-y-1 px-2 pt-2 pb-3">
                        <SectionDisclosure section="raiding" active={isRaidingActive} classes={classes} />
                        <SectionDisclosure section="loot" active={isLootActive} classes={classes} />
                        <ResponsiveNavLink
                            className={classes.responsiveNavLink}
                            href={route("roster.index")}
                            active={isRosterActive}
                        >
                            <Icon icon="users" style="solid" className="mr-2 h-6" />
                            Roster
                        </ResponsiveNavLink>
                    </div>

                    {user && gameVersions.length > 1 && (
                        <div className={cn("border-t px-2 py-3", classes.mobileDivider)}>
                            <GameVersionSwitchList linkClassName={mobileLinkClass} />
                        </div>
                    )}

                    <div className={cn("border-t pt-4 pb-3", classes.mobileDivider)}>
                        {user ? (
                            <div className="space-y-2 px-2">
                                <Link href={route("account.index")} className={cn(mobileLinkClass, "gap-3")}>
                                    <img src={user.avatar} alt="" className="h-10 w-10 rounded-full" />
                                    <span className="flex flex-1 flex-col">
                                        <span className="text-base font-medium text-white">{user.display_name}</span>
                                        {user.highest_role && (
                                            <span
                                                className={`text-sm text-discord-${user.highest_role.replace(/\s+/g, "").toLowerCase()}`}
                                            >
                                                {user.highest_role}
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
                            <div className="px-4">
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

                    <div className={cn("border-t px-2 pt-3", classes.mobileDivider)}>
                        <ResponsiveNavLink
                            className={classes.responsiveNavLink}
                            href={discordInviteUrl}
                            external
                            rel="noopener noreferrer"
                        >
                            <Icon icon="discord" style="brands" className="mr-2 h-6" />
                            <span className="flex-1">Discord</span>
                            <Icon icon="external-link" style="regular" className="h-4" aria-hidden="true" />
                        </ResponsiveNavLink>
                    </div>
                </div>

                <div inert={showingNavigationDropdown} className="contents">
                    {/* Flash Messages */}
                    <FlashMessage type="error" message={flashError} onDismiss={() => setFlashError(null)} />
                    <FlashMessage type="success" message={flashSuccess} onDismiss={() => setFlashSuccess(null)} />

                    <main id="main-content" tabIndex={-1} className="flex flex-1 flex-col focus:outline-none">
                        {children}
                    </main>

                    <Footer
                        classes={classes}
                        discordInviteUrl={discordInviteUrl}
                        gameVersions={gameVersions}
                        permissions={auth?.permissions ?? []}
                    />
                </div>
            </div>

            <SearchPalette open={searchOpen} onClose={() => setSearchOpen(false)} />
        </>
    );
}
