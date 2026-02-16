import { useState, useEffect } from "react";
import { Link, Head, usePage } from "@inertiajs/react";
import Dropdown from "@/Components/Dropdown";
import FlashMessage from "@/Components/FlashMessage";
import Icon from "@/Components/FontAwesome/Icon";
import Pill from "@/Components/Pill";

export default function Master({ title, children }) {
    const { auth, flash, phases } = usePage().props;
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
                        className="flex flex-row items-center border-b border-transparent p-1 text-lg font-bold text-white transition-colors hover:border-white"
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
                        className="inline-flex items-center justify-center rounded-md p-2 text-gray-400 hover:bg-brown-700 hover:text-white focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white lg:hidden"
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
                                className="flex flex-row items-center border-b border-transparent p-1 text-sm font-medium transition-colors hover:border-white"
                            >
                                <Icon icon="users" style="solid" className="mr-2 h-6" />
                                Roster
                            </Link>
                            <Link
                                href={route("daily-quests.index")}
                                className="flex flex-row items-center border-b border-transparent p-1 text-sm font-medium transition-colors hover:border-white"
                            >
                                <img
                                    src="/images/icon_quest.webp"
                                    alt="Quest start icon"
                                    className="mr-2 inline-block h-4 px-1"
                                />
                                Daily Quests
                            </Link>
                            {can?.accessLoot && (
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <button className="flex flex-row items-center border-b border-transparent p-1 text-sm font-medium transition-colors hover:border-white">
                                            <Icon icon="treasure-chest" style="solid" className="mr-2 h-6" />
                                            Loot Bias
                                            <Icon icon="chevron-down" style="regular" className="ml-1 h-6" />
                                        </button>
                                    </Dropdown.Trigger>
                                    <Dropdown.Content align="left">
                                        {phases?.map((phase) => (
                                            <Dropdown.Link
                                                key={phase.id}
                                                href={route("loot.phase", { phase: phase.id })}
                                            >
                                                {phase.description}
                                            </Dropdown.Link>
                                        ))}
                                        {can?.viewAllComments && (
                                            <>
                                                <div className="my-1 border-t border-amber-700" />
                                                <Dropdown.Link href={route("loot.comments.index")}>
                                                    <Icon icon="comments" style="solid" className="mr-2 h-6" />
                                                    All Comments
                                                </Dropdown.Link>
                                            </>
                                        )}
                                    </Dropdown.Content>
                                </Dropdown>
                            )}
                            {can?.accessLoot && (
                                <Link
                                    href="https://thatsmybis.com/24119/regrowth/"
                                    className="flex flex-row items-center border-b border-transparent p-1 text-sm font-medium transition-colors hover:border-white"
                                >
                                    <img
                                        src="/images/logo_thatsmybis.webp"
                                        alt="That's My Bis logo"
                                        className="mr-2 inline-block h-5"
                                    />
                                    That&rsquo;s My Bis
                                </Link>
                            )}
                            <Link
                                href="https://discord.gg/regrowth"
                                className="flex flex-row items-center border-b border-transparent p-1 text-sm font-medium transition-colors hover:border-white"
                            >
                                <Icon icon="discord" style="brands" className="mr-2 h-6" />
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
                                            <Icon icon="chevron-down" style="regular" className="h-6" />
                                        </button>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        {/* <Dropdown.Link href={route('profile.edit')}>
                                            <Icon icon="user-cog" style="regular" className="h-6 mr-2" />
                                            Account Settings
                                        </Dropdown.Link> */}
                                        {impersonating && (
                                            <Dropdown.Link href={route("auth.return-to-self")}>
                                                <Icon icon="undo" style="regular" className="mr-2 h-6" />
                                                Return to my account
                                            </Dropdown.Link>
                                        )}
                                        {can?.accessDashboard && (
                                            <Dropdown.Link href={route("dashboard.index")}>
                                                <Icon icon="cogs" style="regular" className="mr-2 h-6" />
                                                Officers&rsquo; Dashboard
                                            </Dropdown.Link>
                                        )}
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
                    <div className="space-y-1 px-2 pb-3 pt-2">
                        <Link
                            href={route("roster.index")}
                            className="flex flex-row items-center rounded-md px-3 py-2 text-base font-medium text-gray-300 hover:bg-amber-700 hover:text-white"
                        >
                            <Icon icon="users" style="solid" className="mr-2 h-6" />
                            Roster
                        </Link>
                        <Link
                            href={route("daily-quests.index")}
                            className="flex flex-row items-center rounded-md px-3 py-2 text-base font-medium text-gray-300 hover:bg-amber-700 hover:text-white"
                        >
                            <span className="w-[20px] justify-center inline-flex mr-2">
                                <img
                                    src="/images/icon_quest.webp"
                                    alt="Quest start icon"
                                    className="inline-block h-4 px-1"
                                />
                            </span>
                            Daily Quests
                        </Link>
                        {can?.accessLoot && (
                            <>
                                <Link
                                    href={route("loot.index")}
                                    className="flex flex-row items-center rounded-md px-3 py-2 text-base font-medium text-gray-300 hover:bg-amber-700 hover:text-white"
                                >
                                    <Icon icon="treasure-chest" style="solid" className="mr-2 h-6" />
                                    Loot Bias
                                </Link>
                                <div className="ml-2 pl-2 border-l-2 border-amber-800">
                                    <p className="text-sm font-medium text-gray-400 mb-1">Phases</p>
                                    <div className="grid grid-cols-5 gap-1 mb-2">
                                        {phases?.map((phase) => (
                                            <Link
                                                key={phase.id}
                                                href={route("loot.phase", { phase: phase.id })}
                                                className="border border-amber-800 text-center rounded px-3 py-2 text-base font-medium text-gray-300 hover:bg-amber-700 hover:text-white"
                                            >
                                                {phase.number}
                                            </Link>
                                        ))}
                                    </div>
                                    {can?.viewAllComments && (
                                        <Link
                                            href={route("loot.comments.index")}
                                            className="flex flex-row items-center rounded-md pl-1 pr-3 py-2 text-base font-medium text-gray-300 hover:bg-amber-700 hover:text-white"
                                        >
                                            <Icon icon="comments" style="solid" className="mr-2 h-6" />
                                            All Comments
                                        </Link>
                                    )}
                                </div>
                            </>
                        )}
                        {can?.accessLoot && (
                            <Link
                                href="https://thatsmybis.com/24119/regrowth/"
                                className="flex flex-row items-center rounded-md px-3 py-2 text-base font-medium text-gray-300 hover:bg-amber-700 hover:text-white"
                            >
                                <img
                                    src="/images/logo_thatsmybis.webp"
                                    alt="That's My Bis logo"
                                    className="mr-2 inline-block h-5"
                                />
                                That&rsquo;s My Bis
                            </Link>
                        )}
                        <Link
                            href="https://discord.gg/regrowth"
                            className="flex flex-row items-center rounded-md px-3 py-2 text-base font-medium text-gray-300 hover:bg-amber-700 hover:text-white"
                        >
                            <Icon icon="discord" style="brands" className="mr-2 h-6" />
                            Discord
                        </Link>
                    </div>

                    <div className="border-t border-amber-700 pb-3 pt-4">
                        {user ? (
                            <div className="space-y-2 px-2">
                                <div className="flex items-center space-x-3 mx-2">
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
                                        className="flex w-full flex-row items-center rounded-md px-3 py-2 text-left text-sm text-gray-300 hover:bg-amber-700 hover:text-white"
                                    >
                                        <Icon icon="undo" style="regular" className="mr-2" />
                                        Return to my account
                                    </Link>
                                )}
                                {can?.accessDashboard && (
                                    <Link
                                        href={route("dashboard.index")}
                                        className="flex w-full flex-row items-center rounded-md px-3 py-2 text-left text-sm text-gray-300 hover:bg-amber-700 hover:text-white"
                                    >
                                        <Icon icon="cogs" style="regular" className="mr-2" />
                                        Officers' Control Panel
                                    </Link>
                                )}
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
                            <nav className="flex flex-col items-center justify-start gap-6 md:flex-row md:flex-wrap md:justify-between">
                                <Link href="/" className="flex h-8 flex-row items-center p-1 text-gray-400">
                                    <Icon icon="copyright" style="regular" className="mr-2 h-5 w-5" />
                                    <span className="text-nowrap">{new Date().getFullYear()} Regrowth</span>
                                </Link>
                                <Link
                                    href="https://www.warcraftlogs.com/guilds/774848-regrowth"
                                    className="flex h-8 flex-row items-center p-1 text-gray-400 transition-colors hover:text-white"
                                >
                                    <svg
                                        xmlns="http://www.w3.org/2000/svg"
                                        viewBox="0 0 553 552"
                                        fill="currentColor"
                                        className="mr-2 h-[20px] w-[20px]"
                                        aria-hidden="true"
                                    >
                                        <path d="M291.31,249.46l-23.42,15.86-26.02-87.8-44.1,133.45-11.73-38.41h-53.63c-.1-45.61,22.73-88.84,61.02-115.06,45.52-31.17,105.41-34.05,153.82-7.52,45.72,25.05,73.9,73.45,73.47,123.06l-56.6-.45-16.14,46.54-13.65-44.39-26.59,27.54-16.43-52.81Z" />
                                        <path d="M419.94,291.71c-5.47,56.44-41.11,102.79-94.61,120.37-69.49,22.84-148.69-4.95-179.62-73.49-6.6-15.08-10.71-29.94-12.35-46.82l42.11-.09,22.68,67.48,43.16-131.32,18.33,61.97,23.69-16.54,17.79,56.99,26.07-27.34,18.89,65.49,28.17-76.67,45.69-.03Z" />
                                        <path d="M393.29,86.56c-71.53-44.07-161.83-44.13-233.19.01l-36.02-39.37C163.11,20.75,206.93,6.28,253.46,1.47c15.63-.73,30.45-.72,46.09-.02,46.59,4.73,90.45,19.24,129.65,45.74l-35.91,39.37Z" />
                                        <path d="M81.09,427.95l-19.28,19.36C24.79,401.45,4.8,346.01,1.51,287.69c-.46-8.09-.92-15.32.04-23.38,2.86-50.16,17.43-97.88,46.32-140.9l39.45,35.94c-41.3,66.92-44.2,150.44-7.37,220.19-12.79,1.33-24.16,5.33-33,14.23l34.14,34.18Z" />
                                        <path d="M472.33,428.15l33.89-34.36c-8.6-8.67-19.79-12.68-32.88-14.14,36.51-69.5,34.25-152.68-7.33-220.14l39.29-36.16c27.7,40.89,42.51,87.26,46.18,136.03.77,11.38.8,21.9-.04,33.27-4.11,56.77-24.24,110.4-60.02,154.71l-19.1-19.2Z" />
                                        <path d="M428.46,471.65l19.47,19.12c-43.25,35.02-95.72,55.01-151.33,59.74-13.72.76-26.49.77-40.2-.01-55.5-4.78-107.77-24.74-151.01-59.65l19.13-19.49,34.16,34.19c9.21-9.01,12.85-20.07,14.27-32.97,65.39,34.49,142.63,34.13,207.4,0,1.25,12.93,5.05,23.8,14.04,33.07l34.05-34Z" />
                                        <path d="M413.49,196.66c-13.24-23.86-32.99-43.43-57.8-57.39l87.48-95.19,83.7-18.45-18.41,83.74-94.97,87.29Z" />
                                        <path d="M197.45,139.1c-24.86,13.79-44.14,32.9-57.97,57.26L44.8,109.35,26.38,25.65l83.71,18.41,87.36,95.04Z" />
                                        <path d="M124.63,460.81l-71.19,71.13-32.83-32.65,71.19-71.27-33.49-33.62c9.65-6.48,20.98-7.82,32.74-7.24l74.07,74.04c1.16,11.16-.45,22.84-6.9,33.12l-33.6-33.52Z" />
                                        <path d="M500.03,532.15l-71.39-71.34-33.67,33.57c-6.32-10.14-7.66-21.26-7.11-32.84l74.26-74.27c11.62-.66,22.66.8,32.86,7.06l-33.54,33.71,71.29,71.32-32.7,32.8Z" />
                                        <path d="M159.67,436.81l-43.95-43.95,30.76-33.62c11.29,19.47,27.12,34.89,46.59,46.93l-33.4,30.64Z" />
                                        <path d="M393.53,436.84l-33.28-30.67c19.2-11.88,34.65-26.96,46.65-46.75l30.65,33.42-44.02,43.99Z" />
                                    </svg>
                                    Warcraft Logs
                                </Link>
                                <Link
                                    href="https://discord.gg/regrowth"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    className="flex h-8 flex-row items-center p-1 text-gray-400 transition-colors hover:text-white"
                                >
                                    <Icon icon="discord" style="brands" className="mr-2 h-5 w-5" />
                                    <span className="text-nowrap">Discord</span>
                                </Link>
                                <Link
                                    href={route("privacypolicy")}
                                    className="flex h-8 flex-row items-center p-1 text-gray-400 transition-colors hover:text-white"
                                >
                                    <Icon icon="user-secret" style="solid" className="mr-2 h-5 w-5" />
                                    <span className="text-nowrap">Privacy policy</span>
                                </Link>
                                <Link
                                    href={route("battlenet-usage")}
                                    className="flex h-8 flex-row items-center p-1 text-gray-400 transition-colors hover:text-white"
                                >
                                    <Icon icon="battle-net" style="brands" className="mr-2 h-5 w-5" />
                                    <span className="text-nowrap">Battle.net API Usage</span>
                                </Link>

                                <Link
                                    href="https://benargo.com"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="Ben Argo"
                                    className="mt-0 flex h-8 flex-row items-center p-1 text-gray-400 transition-colors hover:text-white"
                                >
                                    <Icon icon="safari" style="brands" className="mr-2 h-5 w-5" />
                                    <span className="text-nowrap">A Fizzywigs Production</span>
                                </Link>
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
        </>
    );
}
