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

export default function Master({ title, children }) {
    const { auth, flash } = usePage().props;
    const user = auth?.user;
    const impersonating = auth?.impersonating;

    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const [flashError, setFlashError] = useState(flash?.error);
    const [flashSuccess, setFlashSuccess] = useState(flash?.success);
    const [searchOpen, setSearchOpen] = useState(false);

    useEffect(() => {
        document.body.classList.add("bg-brown", "bg-brown-texture");
        return () => {
            document.body.classList.remove("bg-brown", "bg-brown-texture");
        };
    }, []);

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

            <div className="min-h-screen text-white">
                <nav className="flex flex-wrap items-center justify-between px-4 py-3 lg:px-6">
                    <Link
                        className="flex flex-row items-center border-b border-transparent p-1 text-lg font-bold text-white transition-colors hover:border-white"
                        href="/"
                    >
                        <img src="/images/guild_emblem.webp" alt="Guild Emblem" className="mr-1 inline-block max-h-8" />
                        Regrowth
                    </Link>

                    {/* Mobile search + menu buttons */}
                    <div className="ml-auto flex items-center gap-1 lg:hidden">
                        <button
                            className="hover:bg-brown-700 inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:text-white focus:ring-2 focus:ring-white focus:outline-hidden focus:ring-inset"
                            type="button"
                            onClick={() => setSearchOpen(true)}
                            aria-label="Search"
                        >
                            <Icon icon="search" style="regular" className="h-6 w-6" />
                        </button>
                        <button
                            className="hover:bg-brown-700 inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:text-white focus:ring-2 focus:ring-white focus:outline-hidden focus:ring-inset"
                            type="button"
                            onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                            aria-controls="mobile-menu"
                            aria-expanded={showingNavigationDropdown}
                            aria-label="Toggle navigation"
                        >
                            <Icon
                                icon="bars"
                                style="regular"
                                className={`${showingNavigationDropdown ? "hidden" : "block"} h-6 w-6`}
                            />
                            <Icon
                                icon="times"
                                style="regular"
                                className={`${showingNavigationDropdown ? "block" : "hidden"} h-6 w-6`}
                            />
                        </button>
                    </div>

                    {/* Desktop menu */}
                    <div className="hidden lg:ml-10 lg:flex lg:flex-1 lg:items-center lg:justify-between">
                        <div className="flex gap-4 space-x-1">
                            <NavLink href={route("characters.index")}>
                                <Icon icon="users" style="solid" className="mr-2 h-6" />
                                Roster
                            </NavLink>
                            <NavLink href={route("daily-quests.index")}>
                                <img
                                    src="/images/icon_quest.webp"
                                    alt="Quest start icon"
                                    className="mr-2 inline-block h-4 px-1"
                                />
                                Daily Quests
                            </NavLink>
                            <NavLink href={route("raiding.index")}>
                                <Icon icon="dragon" style="solid" className="mr-2 h-6" />
                                Raiding
                            </NavLink>
                            <NavLink href={route("loot.index")}>
                                <Icon icon="treasure-chest" style="solid" className="mr-2 h-6" />
                                Loot Bias
                            </NavLink>
                            <NavLink href="https://discord.gg/pM6haPnQRt" external rel="noopener noreferrer">
                                <Icon icon="discord" style="brands" className="mr-2 h-6" />
                                Discord
                            </NavLink>
                        </div>

                        <div className="flex items-center gap-3">
                            <button
                                type="button"
                                onClick={() => setSearchOpen(true)}
                                className="hover:bg-brown-700 border-brown-600 bg-brown-800 flex min-h-6 items-center gap-2 rounded border px-3 py-1.5 py-2 text-sm text-gray-400 transition-colors hover:text-white focus:ring-1 focus:ring-amber-500 focus:outline-hidden"
                            >
                                <Icon icon="search" style="solid" className="h-4 w-4" />
                                <span>Search</span>
                                <span className="bg-brown-700 rounded px-1.5 py-0.5 text-xs text-gray-500">⌘K</span>
                            </button>
                            {user ? (
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <button className="flex items-center space-x-2 text-sm font-medium text-gray-300 transition-colors hover:text-white">
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

                                    <Dropdown.Content>
                                        <Dropdown.Link href={route("account.index")}>
                                            <Icon icon="user-cog" style="regular" className="mr-2 h-6" />
                                            Account Settings
                                        </Dropdown.Link>
                                        {impersonating && (
                                            <Dropdown.Link href={route("auth.return-to-self")}>
                                                <Icon icon="undo" style="regular" className="mr-2 h-6" />
                                                Return to my account
                                            </Dropdown.Link>
                                        )}
                                        <Can permission="view-officer-dashboard">
                                            <Dropdown.Link href={route("management.dashboard")}>
                                                <Icon icon="cogs" style="regular" className="mr-2" />
                                                <div className="flex flex-col items-start gap-1">
                                                    <span>Control Panel</span>
                                                </div>
                                            </Dropdown.Link>
                                        </Can>
                                        <Dropdown.Link href={route("logout")} method="post" as="button">
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

                {/* Mobile menu */}
                <div className={`${showingNavigationDropdown ? "block" : "hidden"} lg:hidden`} id="mobile-menu">
                    <div className="space-y-1 px-2 pt-2 pb-3">
                        <ResponsiveNavLink href={route("characters.index")}>
                            <Icon icon="users" style="solid" className="mr-2 h-6" />
                            Roster
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href={route("daily-quests.index")}>
                            <span className="mr-2 inline-flex w-5 justify-center">
                                <img
                                    src="/images/icon_quest.webp"
                                    alt="Quest start icon"
                                    className="inline-block h-4 px-1"
                                />
                            </span>
                            Daily Quests
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href={route("raiding.index")}>
                            <Icon icon="dragon" style="solid" className="mr-2 h-6" />
                            Raiding
                        </ResponsiveNavLink>
                        <ResponsiveNavLink href={route("loot.index")}>
                            <Icon icon="treasure-chest" style="solid" className="mr-2 h-6" />
                            Loot Bias
                        </ResponsiveNavLink>
                        <button
                            type="button"
                            onClick={() => {
                                setShowingNavigationDropdown(false);
                                setSearchOpen(true);
                            }}
                            className="flex w-full flex-row items-center rounded-md px-3 py-2 text-sm font-medium text-gray-300 hover:bg-amber-700 hover:text-white"
                        >
                            <Icon icon="search" style="solid" className="mr-2 h-6" />
                            Search
                        </button>
                        <ResponsiveNavLink href="https://discord.gg/pM6haPnQRt" external rel="noopener noreferrer">
                            <Icon icon="discord" style="brands" className="mr-2 h-6" />
                            Discord
                        </ResponsiveNavLink>
                    </div>

                    <div className="border-t border-amber-700 pt-4 pb-3">
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
                                    className="flex w-full flex-row items-center rounded-md px-3 py-2 text-left text-sm text-gray-300 hover:bg-amber-700 hover:text-white"
                                >
                                    <Icon icon="user-cog" style="regular" className="mr-2" />
                                    Account Settings
                                </Link>
                                {impersonating && (
                                    <Link
                                        href={route("auth.return-to-self")}
                                        className="flex w-full flex-row items-center rounded-md px-3 py-2 text-left text-sm text-gray-300 hover:bg-amber-700 hover:text-white"
                                    >
                                        <Icon icon="undo" style="regular" className="mr-2" />
                                        Return to my account
                                    </Link>
                                )}
                                <Can permission="view-officer-dashboard">
                                    <Link
                                        href={route("management.dashboard")}
                                        className="flex w-full flex-row items-center rounded-md px-3 py-2 text-left text-sm text-gray-300 hover:bg-amber-700 hover:text-white"
                                    >
                                        <Icon icon="cogs" style="regular" className="mr-2" />
                                        Control Panel
                                    </Link>
                                </Can>
                                <Link
                                    href={route("logout")}
                                    method="post"
                                    as="button"
                                    className="flex w-full flex-row items-center rounded-md px-3 py-2 text-left text-sm text-gray-300 hover:bg-amber-700 hover:text-white"
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

                <main>{children}</main>

                <footer className="p-5" id="footer">
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
                                    src="/images/logo_tbcclassic.webp"
                                    alt="World of Warcraft Classic logo"
                                    className="h-20 w-1/2 flex-1 object-contain md:mr-4 md:w-auto"
                                />
                                <span className="sr-only">World of Warcraft: Classic</span>
                            </div>
                            {/* Footer Links */}
                            <nav className="flex flex-col items-center justify-start gap-4 md:flex-row md:flex-wrap md:gap-2">
                                <Link href="/" className="flex h-8 flex-row items-center p-1 text-gray-400 md:ml-2">
                                    <Icon icon="copyright" style="regular" className="mr-2 h-5 w-5" />
                                    <span className="text-nowrap">{new Date().getFullYear()} Regrowth</span>
                                </Link>
                                <a
                                    href="https://www.warcraftlogs.com/guilds/774848-regrowth"
                                    className="flex h-8 flex-row items-center gap-2 p-1 text-gray-400 transition-colors hover:text-white md:ml-2"
                                    rel="noopener noreferrer"
                                >
                                    <WarcraftLogsLogo className="h-5 w-5" />
                                    <span className="text-nowrap">Warcraft Logs</span>
                                </a>
                                <a
                                    href="https://discord.gg/pM6haPnQRt"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex h-8 flex-row items-center gap-2 p-1 text-gray-400 transition-colors hover:text-white md:ml-2"
                                >
                                    <Icon icon="discord" style="brands" className="h-5 w-5" />
                                    <span className="text-nowrap">Discord</span>
                                </a>
                                <Link
                                    href={route("privacypolicy")}
                                    className="flex h-8 flex-row items-center gap-2 p-1 text-gray-400 transition-colors hover:text-white md:ml-2"
                                >
                                    <Icon icon="user-secret" style="solid" className="h-5 w-5" />
                                    <span className="text-nowrap">Privacy policy</span>
                                </Link>
                                <Link
                                    href={route("battlenet-usage")}
                                    className="flex h-8 flex-row items-center gap-2 p-1 text-gray-400 transition-colors hover:text-white md:ml-2"
                                >
                                    <Icon icon="battle-net" style="brands" className="h-5 w-5" />
                                    <span className="text-nowrap">Battle.net API Usage</span>
                                </Link>

                                <a
                                    href="https://benargo.com"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="Ben Argo"
                                    className="mt-0 flex h-8 flex-row items-center gap-2 p-1 text-gray-400 transition-colors hover:text-white md:ml-2"
                                >
                                    <Icon icon="safari" style="brands" className="h-5 w-5" />
                                    <span className="text-nowrap">A Fizzywigs Production</span>
                                </a>
                            </nav>
                        </div>
                        {/* Disclaimer */}
                        <div className="py-4">
                            <p className="text-sm text-gray-500">
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
