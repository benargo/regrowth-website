import { useState, useEffect } from "react";
import { Link, Head, usePage } from "@inertiajs/react";
import Dropdown from "@/Components/Dropdown";
import FlashMessage from "@/Components/FlashMessage";
import Icon from "@/Components/FontAwesome/Icon";
import Pill from "@/Components/Pill";

export default function Master({ title, children }) {
    const { auth, flash } = usePage().props;
    const user = auth?.user;
    const can = auth?.can;
    const impersonating = auth?.impersonating;

    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const [flashError, setFlashError] = useState(flash?.error);
    const [flashSuccess, setFlashSuccess] = useState(flash?.success);

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

    return (
        <>
            <Head title={title} />

            <div className="min-h-screen text-white">
                <nav className="flex flex-wrap items-center justify-between px-4 py-3 lg:px-6">
                    <Link
                        className="flex items-center border-b border-transparent p-1 text-lg font-bold text-white transition-colors hover:border-white"
                        href="/"
                    >
                        <img
                            src="/images/guild_emblem.webp"
                            alt="Guild Emblem"
                            className="mr-1 inline-block max-h-[32px]"
                        />
                        Regrowth
                    </Link>

                    {/* Mobile menu button */}
                    <button
                        className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-gray-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white lg:hidden"
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

                    {/* Desktop menu */}
                    <div className="hidden lg:ml-10 lg:flex lg:flex-1 lg:items-center lg:justify-between">
                        <div className="flex gap-4 space-x-1">
                            <Link
                                href={route("roster.index")}
                                className="border-b border-transparent p-1 text-sm font-medium transition-colors hover:border-white"
                            >
                                <Icon icon="users" style="solid" className="mr-2" />
                                Roster
                            </Link>
                            {can?.accessLoot && (
                                <Link
                                    href="/loot"
                                    className="border-b border-transparent p-1 text-sm font-medium transition-colors hover:border-white"
                                >
                                    <Icon icon="treasure-chest" style="solid" className="mr-2" />
                                    Loot Bias
                                </Link>
                            )}
                            <Link
                                href="https://discord.gg/regrowth"
                                className="border-b border-transparent p-1 text-sm font-medium transition-colors hover:border-white"
                            >
                                <Icon icon="discord" style="brands" className="mr-2" />
                                Discord
                            </Link>
                        </div>

                        <div className="flex items-center">
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
                                            <Icon icon="chevron-down" style="regular" className="text-xs" />
                                        </button>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        {/* <Dropdown.Link href={route('profile.edit')}>
                                            <Icon icon="user-cog" style="regular" className="mr-2" />
                                            Account Settings
                                        </Dropdown.Link> */}
                                        {impersonating && (
                                            <Dropdown.Link href={route("auth.return-to-self")}>
                                                <Icon icon="undo" style="regular" className="mr-2" />
                                                Return to my account
                                            </Dropdown.Link>
                                        )}
                                        {can?.accessDashboard && (
                                            <Dropdown.Link href={route("dashboard.index")}>
                                                <Icon icon="cogs" style="regular" className="mr-2" />
                                                Officers&rsquo; Dashboard
                                            </Dropdown.Link>
                                        )}
                                        <Dropdown.Link href={route("logout")} method="post" as="button">
                                            <Icon icon="sign-out" style="regular" className="mr-2" />
                                            Logout
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            ) : (
                                <a
                                    href={route("login")}
                                    className="flex items-center space-x-2 rounded-md bg-[#5865F2] px-4 py-2 text-white transition-colors hover:bg-[#4752C4]"
                                >
                                    <Icon icon="discord" style="brands" />
                                    <span>Login with Discord</span>
                                </a>
                            )}
                        </div>
                    </div>
                </nav>

                {/* Mobile menu */}
                <div className={`${showingNavigationDropdown ? "block" : "hidden"} lg:hidden`} id="mobile-menu">
                    <div className="space-y-1 px-2 pb-3 pt-2">
                        <Link
                            href={route("roster.index")}
                            className="block rounded-md px-3 py-2 text-base font-medium text-gray-300 hover:bg-amber-700 hover:text-white"
                        >
                            <Icon icon="users" style="solid" className="mr-2" />
                            Roster
                        </Link>
                        {can?.accessLoot && (
                            <Link
                                href={route("loot.index")}
                                className="block rounded-md px-3 py-2 text-base font-medium text-gray-300 hover:bg-amber-700 hover:text-white"
                            >
                                <Icon icon="treasure-chest" style="solid" className="mr-2" />
                                Loot Bias
                            </Link>
                        )}
                        <Link
                            href="https://discord.gg/regrowth"
                            className="block rounded-md px-3 py-2 text-base font-medium text-gray-300 hover:bg-amber-700 hover:text-white"
                        >
                            <Icon icon="discord" style="brands" className="mr-2" />
                            Discord
                        </Link>
                    </div>

                    <div className="border-t border-amber-700 pb-3 pt-4">
                        {user ? (
                            <div className="space-y-2 px-4">
                                <div className="flex items-center space-x-3">
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
                                {/* <Link
                                    href={route('profile.edit')}
                                    className="block px-3 py-2 text-sm text-gray-300 hover:text-white hover:bg-amber-700 rounded-md"
                                >
                                    <Icon icon="user-cog" style="regular" className="mr-2" />
                                    Account Settings
                                </Link> */}
                                {impersonating && (
                                    <Link
                                        href={route("auth.return-to-self")}
                                        className="block w-full rounded-md px-3 py-2 text-left text-sm text-gray-300 hover:bg-amber-700 hover:text-white"
                                    >
                                        <Icon icon="undo" style="regular" className="mr-2" />
                                        Return to my account
                                    </Link>
                                )}
                                {can?.accessDashboard && (
                                    <Link
                                        href={route("dashboard.index")}
                                        className="block w-full rounded-md px-3 py-2 text-left text-sm text-gray-300 hover:bg-amber-700 hover:text-white"
                                    >
                                        <Icon icon="cogs" style="regular" className="mr-2" />
                                        Officers' Control Panel
                                    </Link>
                                )}
                                <Link
                                    href={route("logout")}
                                    method="post"
                                    as="button"
                                    className="block w-full rounded-md px-3 py-2 text-left text-sm text-gray-300 hover:bg-amber-700 hover:text-white"
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

                <footer className="mx-3 py-5 md:mx-5" id="footer">
                    <div className="container mx-auto">
                        {/* Footer Links */}
                        <div className="my-5 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            {/* Column 1 - Legal & Account */}
                            <ul className="space-y-2 text-center lg:col-start-2">
                                <li className="px-3 py-2 text-gray-300">
                                    <Icon icon="copyright" style="regular" className="mr-2 w-5" />
                                    {new Date().getFullYear()} Regrowth
                                </li>
                            </ul>

                            {/* Column 2 - External Links */}
                            <ul className="space-y-2 text-center">
                                <li>
                                    <a
                                        href="https://benargo.com"
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        title="Ben Argo"
                                        className="block px-3 py-2 text-gray-400 transition-colors hover:text-white"
                                    >
                                        <Icon icon="safari" style="brands" className="mr-2 w-5" />
                                    </a>
                                </li>
                            </ul>
                        </div>

                        {/* Logos Section */}
                        <div className="my-5 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-4">
                            <div className="text-center lg:col-start-2">
                                <Link href="/" title="Regrowth">
                                    <img
                                        src="/images/guild_emblem.webp"
                                        alt="Guild Emblem"
                                        className="ml-4 inline-block max-h-36"
                                    />
                                </Link>
                            </div>
                            <div className="text-center">
                                <img
                                    src="/images/logo_tbcclassic.webp"
                                    alt="World of Warcraft Classic logo"
                                    className="inline-block max-h-36"
                                />
                                <span className="sr-only">World of Warcraft: Classic</span>
                            </div>
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
        </>
    );
}
