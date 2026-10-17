import { useState, useEffect } from "react";
import { Link, Head, usePage } from "@inertiajs/react";
import Dropdown from "@/Components/Dropdown";
import FlashMessage from "@/Components/FlashMessage";
import Icon from "@/Components/FontAwesome/Icon";
import NavLink from "@/Components/NavLink";
import Pill from "@/Components/Pill";
import ResponsiveNavLink from "@/Components/ResponsiveNavLink";
import { Can } from "@/Components/Authorizable";
import WarcraftLogsLogo from "@/Components/WarcraftLogs/Logo";
import SearchPalette from "@/Components/Search/SearchPalette";
import cn from "@/Helpers/cn";

const NAV_CLASSES = {
    classic: {
        wrapper: "bg-brown-800 bg-parchment",
        nav: "bg-brown-900/80 border-brown-700/50",
        iconButton: "hover:bg-brown-700 text-gray-400",
        searchButton: "hover:bg-brown-700 border-brown-600 bg-brown-800 focus:ring-amber-500 text-gray-400",
        searchShortcut: "bg-brown-700 text-gray-500",
        navLink: "",
        responsiveNavLink: "",
        dropdownContent: undefined,
        dropdownLink: "",
        mobileMenu: "bg-brown-900",
        mobileLink: "hover:bg-amber-700 text-gray-300",
        mobileDivider: "border-amber-700",
        footer: "border-transparent",
        footerLink: "text-amber-600",
    },
    forever: {
        wrapper: "bg-parchment-forever",
        nav: "bg-forever-900/80 border-forever-700/50",
        iconButton: "hover:bg-forever-700 text-verdant-400",
        searchButton: "hover:bg-forever-700 border-forever-600 bg-forever-800 focus:ring-camel-500 text-verdant-400",
        searchShortcut: "bg-forever-700 text-verdant-500",
        navLink: "hover:bg-forever-700/80",
        responsiveNavLink: "text-verdant-300 hover:bg-camel-700",
        dropdownContent: "py-1 bg-forever-800",
        dropdownLink: "bg-forever-800 hover:bg-forever-800 focus:bg-forever-700",
        mobileMenu: "bg-forever-900",
        mobileLink: "hover:bg-camel-700 text-verdant-300",
        mobileDivider: "border-camel-700",
        footer: "bg-footer-forever border-footer-forever",
        footerLink: "text-camel-600",
    },
};

export default function Master({ title, isHomepage = false, children }) {
    const { auth, flash, discordInviteUrl } = usePage().props;
    const classes = isHomepage ? NAV_CLASSES.forever : NAV_CLASSES.classic;
    const user = auth?.user;
    const impersonating = auth?.impersonating;

    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const [flashError, setFlashError] = useState(flash?.error);
    const [flashSuccess, setFlashSuccess] = useState(flash?.success);
    const [searchOpen, setSearchOpen] = useState(false);

    // Update flash messages when props change
    useEffect(() => {
        setFlashError(flash?.error);
        setFlashSuccess(flash?.success);
    }, [flash?.error, flash?.success]);

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

    return (
        <>
            <Head title={title} />

            <div className={cn("flex min-h-screen flex-col text-white", classes.wrapper)}>
                <div className="fixed inset-x-0 top-4 z-20 px-4">
                    <nav
                        className={cn(
                            "grid grid-cols-[auto_1fr_auto] items-center overflow-visible rounded-lg border py-3 pr-3 pl-4 shadow-lg backdrop-blur-xl lg:flex lg:flex-wrap lg:gap-10 lg:px-4",
                            classes.nav,
                        )}
                    >
                        {/* Mobile menu toggle */}
                        <button
                            className={cn(
                                "inline-flex items-center justify-center rounded-md p-2 hover:text-white focus:ring-2 focus:ring-white focus:outline-hidden focus:ring-inset lg:hidden",
                                classes.iconButton,
                            )}
                            type="button"
                            onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                            aria-controls="mobile-menu"
                            aria-expanded={showingNavigationDropdown}
                            aria-label="Toggle navigation"
                        >
                            <span className={showingNavigationDropdown ? "hidden" : "block"}>
                                <Icon icon="bars" style="regular" className="h-6 w-6" />
                            </span>
                            <span className={showingNavigationDropdown ? "block" : "hidden"}>
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
                                className="mr-2 inline-block max-h-8 lg:max-h-14"
                            />
                            <span className="hidden lg:inline">Regrowth</span>
                        </Link>

                        {/* Mobile search button */}
                        <button
                            className={cn(
                                "inline-flex items-center justify-center justify-self-end rounded-md p-2 hover:text-white focus:ring-2 focus:ring-white focus:outline-hidden focus:ring-inset lg:hidden",
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
                            <div className="flex items-center gap-1">
                                <NavLink className={classes.navLink} href={route("roster.index")}>
                                    <Icon icon="users" style="solid" className="mr-2 h-6" />
                                    Roster
                                </NavLink>
                                <NavLink className={classes.navLink} href={route("raiding.index")}>
                                    <Icon icon="dragon" style="solid" className="mr-2 h-9" />
                                    Raiding
                                </NavLink>
                                <NavLink className={classes.navLink} href={route("loot.index")}>
                                    <Icon icon="treasure-chest" style="solid" className="mr-2 h-6" />
                                    Loot
                                </NavLink>
                                <NavLink
                                    className={classes.navLink}
                                    href={discordInviteUrl}
                                    external
                                    rel="noopener noreferrer"
                                >
                                    <Icon icon="discord" style="brands" className="mr-2 h-6" />
                                    Discord
                                </NavLink>
                            </div>

                            <div className="flex items-center gap-3">
                                <button
                                    type="button"
                                    onClick={() => setSearchOpen(true)}
                                    className={cn(
                                        "flex min-h-6 items-center gap-2 rounded border px-3 py-2 text-sm transition-colors hover:text-white focus:ring-1 focus:outline-hidden",
                                        classes.searchButton,
                                    )}
                                >
                                    <Icon icon="search" style="solid" className="h-4 w-4" />
                                    <span>Search</span>
                                    <span className={cn("rounded px-1.5 py-0.5 text-xs", classes.searchShortcut)}>
                                        ⌘K
                                    </span>
                                </button>
                                {user ? (
                                    <Dropdown>
                                        <Dropdown.Trigger>
                                            <button className="flex items-center space-x-2 text-sm font-medium text-white transition-colors hover:text-white">
                                                <img
                                                    src={user.avatar}
                                                    alt={user.display_name}
                                                    className="h-8 w-8 rounded-full"
                                                />
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
                        "fixed inset-x-0 top-19 z-20 max-h-[calc(100vh-4.75rem)] overflow-y-auto lg:hidden",
                        showingNavigationDropdown ? "block" : "hidden",
                        classes.mobileMenu,
                    )}
                    id="mobile-menu"
                >
                    <div className="space-y-1 px-2 pt-2 pb-3">
                        <ResponsiveNavLink className={classes.responsiveNavLink} href={route("roster.index")}>
                            <Icon icon="users" style="solid" className="mr-2 h-6" />
                            Roster
                        </ResponsiveNavLink>
                        <ResponsiveNavLink className={classes.responsiveNavLink} href={route("raiding.index")}>
                            <Icon icon="dragon" style="solid" className="mr-2 h-6" />
                            Raiding
                        </ResponsiveNavLink>
                        <ResponsiveNavLink className={classes.responsiveNavLink} href={route("loot.index")}>
                            <Icon icon="treasure-chest" style="solid" className="mr-2 h-6" />
                            Loot
                        </ResponsiveNavLink>
                        <button
                            type="button"
                            onClick={() => {
                                setShowingNavigationDropdown(false);
                                setSearchOpen(true);
                            }}
                            className={cn(
                                "flex w-full flex-row items-center rounded-md px-3 py-2 text-sm font-medium hover:text-white",
                                classes.mobileLink,
                            )}
                        >
                            <Icon icon="search" style="solid" className="mr-2 h-6" />
                            Search
                        </button>
                        <ResponsiveNavLink
                            className={classes.responsiveNavLink}
                            href={discordInviteUrl}
                            external
                            rel="noopener noreferrer"
                        >
                            <Icon icon="discord" style="brands" className="mr-2 h-6" />
                            Discord
                        </ResponsiveNavLink>
                    </div>

                    <div className={cn("border-t pt-4 pb-3", classes.mobileDivider)}>
                        {user ? (
                            <div className="space-y-2 px-2">
                                <div className="mx-2 flex items-center space-x-3">
                                    <img src={user.avatar} alt={user.display_name} className="h-10 w-10 rounded-full" />
                                    <div>
                                        <div className="text-base font-medium text-white">{user.display_name}</div>
                                        {user.highest_role && (
                                            <div
                                                className={`text-sm text-discord-${user.highest_role.replace(/\s+/g, "").toLowerCase()}`}
                                            >
                                                {user.highest_role}
                                            </div>
                                        )}
                                    </div>
                                </div>
                                <Link
                                    href={route("account.index")}
                                    className={cn(
                                        "flex w-full flex-row items-center rounded-md px-3 py-2 text-left text-sm hover:text-white",
                                        classes.mobileLink,
                                    )}
                                >
                                    <Icon icon="user-cog" style="regular" className="mr-2" />
                                    Account Settings
                                </Link>
                                {impersonating && (
                                    <Link
                                        href={route("auth.return-to-self")}
                                        className={cn(
                                            "flex w-full flex-row items-center rounded-md px-3 py-2 text-left text-sm hover:text-white",
                                            classes.mobileLink,
                                        )}
                                    >
                                        <Icon icon="undo" style="regular" className="mr-2" />
                                        Return to my account
                                    </Link>
                                )}
                                <Can permission="view-officer-dashboard">
                                    <Link
                                        href={route("management.dashboard")}
                                        className={cn(
                                            "flex w-full flex-row items-center rounded-md px-3 py-2 text-left text-sm hover:text-white",
                                            classes.mobileLink,
                                        )}
                                    >
                                        <Icon icon="cogs" style="regular" className="mr-2" />
                                        Control Panel
                                    </Link>
                                </Can>
                                <Link
                                    href={route("logout")}
                                    method="post"
                                    as="button"
                                    className={cn(
                                        "flex w-full flex-row items-center rounded-md px-3 py-2 text-left text-sm hover:text-white",
                                        classes.mobileLink,
                                    )}
                                >
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
                </div>

                {/* Flash Messages */}
                <FlashMessage type="error" message={flashError} onDismiss={() => setFlashError(null)} />
                <FlashMessage type="success" message={flashSuccess} onDismiss={() => setFlashSuccess(null)} />

                <div className="flex flex-1 flex-col">{children}</div>

                <footer className={cn("border-t-8 py-5", classes.footer)} id="footer">
                    <div className="container mx-auto">
                        <div className="my-4 flex flex-col-reverse items-center justify-between md:my-6 md:flex-row">
                            {/* Logos Section */}
                            <div className="my-4 flex flex-none items-center md:my-0 md:gap-6">
                                <Link href="/" title="Regrowth" className="flex flex-1 items-center md:flex-none">
                                    <img
                                        src="/images/guild_emblem.webp"
                                        alt="Guild Emblem"
                                        className="h-20 w-1/2 object-contain md:w-auto"
                                    />
                                    <span className="sr-only">Regrowth</span>
                                </Link>
                                <img
                                    src="/images/logo_forever.webp"
                                    alt="World of Warcraft: Forever logo"
                                    className="h-20 w-1/2 flex-1 object-contain md:mr-4 md:w-auto"
                                />
                                <span className="sr-only">
                                    {isHomepage ? "World of Warcraft: Forever" : "World of Warcraft: Classic"}
                                </span>
                            </div>
                            {/* Footer Links */}
                            <nav className="flex flex-col items-center justify-start gap-4 md:flex-row md:flex-wrap md:gap-2">
                                <div className={cn("flex h-8 flex-row items-center p-1 md:ml-2", classes.footerLink)}>
                                    <Icon icon="copyright" style="regular" className="mr-2 h-5 w-5" />
                                    <span className="text-nowrap">{new Date().getFullYear()} Regrowth</span>
                                </div>
                                <a
                                    href="https://www.warcraftlogs.com/guilds/774848-regrowth"
                                    className={cn(
                                        "flex h-8 flex-row items-center gap-2 p-1 transition-colors hover:text-white md:ml-2",
                                        classes.footerLink,
                                    )}
                                    rel="noopener noreferrer"
                                >
                                    <WarcraftLogsLogo className="h-5 w-5" />
                                    <span className="text-nowrap">Warcraft Logs</span>
                                </a>
                                <a
                                    href={discordInviteUrl}
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className={cn(
                                        "flex h-8 flex-row items-center gap-2 p-1 transition-colors hover:text-white md:ml-2",
                                        classes.footerLink,
                                    )}
                                >
                                    <Icon icon="discord" style="brands" className="h-5 w-5" />
                                    <span className="text-nowrap">Discord</span>
                                </a>
                                <Link
                                    href={route("privacypolicy")}
                                    className={cn(
                                        "flex h-8 flex-row items-center gap-2 p-1 transition-colors hover:text-white md:ml-2",
                                        classes.footerLink,
                                    )}
                                >
                                    <Icon icon="user-secret" style="solid" className="h-5 w-5" />
                                    <span className="text-nowrap">Privacy policy</span>
                                </Link>
                                <Link
                                    href={route("battlenet-usage")}
                                    className={cn(
                                        "flex h-8 flex-row items-center gap-2 p-1 transition-colors hover:text-white md:ml-2",
                                        classes.footerLink,
                                    )}
                                >
                                    <Icon icon="battle-net" style="brands" className="h-5 w-5" />
                                    <span className="text-nowrap">Battle.net API Usage</span>
                                </Link>

                                <a
                                    href="https://benargo.com"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="Ben Argo"
                                    className={cn(
                                        "mt-0 flex h-8 flex-row items-center gap-2 p-1 transition-colors hover:text-white md:ml-2",
                                        classes.footerLink,
                                    )}
                                >
                                    <Icon icon="safari" style="brands" className="h-5 w-5" />
                                    <span className="text-nowrap">A Fizzywigs Production</span>
                                </a>
                            </nav>
                        </div>
                        <div className="mt-4 md:mt-2">
                            <p className="text-center text-xs text-gray-400 md:text-left">
                                Disclaimer: Classic is a trademark, and World of Warcraft and Warcraft are trademarks or
                                registered trademarks of Blizzard Entertainment, Inc., in the U.S. and/or other
                                countries. All related materials, logos, and images are copyright &copy; Blizzard
                                Entertainment, Inc. Regrowth is in no way associated with or endorsed by Blizzard
                                Entertainment.
                            </p>
                        </div>
                    </div>
                </footer>
            </div>

            <SearchPalette open={searchOpen} onClose={() => setSearchOpen(false)} />
        </>
    );
}
