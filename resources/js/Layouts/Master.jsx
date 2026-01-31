import { useState, useEffect } from 'react';
import { Link, Head, usePage } from '@inertiajs/react';
import Dropdown from '@/Components/Dropdown';
import FlashMessage from '@/Components/FlashMessage';

export default function Master({ title, children }) {
    const { auth, flash } = usePage().props;
    const user = auth?.user;
    const can = auth?.can;
    const impersonating = auth?.impersonating;

    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const [flashError, setFlashError] = useState(flash?.error);
    const [flashSuccess, setFlashSuccess] = useState(flash?.success);

    useEffect(() => {
        document.body.classList.add('bg-brown', 'bg-brown-texture');
        return () => {
            document.body.classList.remove('bg-brown', 'bg-brown-texture');
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
                <nav className="flex items-center justify-between flex-wrap px-4 py-3 lg:px-6">
                    <Link
                        className="flex items-center p-1 text-white font-bold text-lg border-b border-transparent hover:border-white transition-colors"
                        href="/"
                    >
                        <img
                            src="/images/guild_emblem.webp"
                            alt="Guild Emblem"
                            className="inline-block max-h-[32px] mr-1"
                        />
                        Regrowth
                    </Link>

                    {/* Mobile menu button */}
                    <button
                        className="lg:hidden inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-white hover:bg-gray-700 focus:outline-none focus:ring-2 focus:ring-inset focus:ring-white"
                        type="button"
                        onClick={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                        aria-controls="mobile-menu"
                        aria-expanded={showingNavigationDropdown}
                        aria-label="Toggle navigation"
                    >
                        <i className={`${showingNavigationDropdown ? 'hidden' : 'block'} far fa-bars h-6 w-6`}></i>
                        <i className={`${showingNavigationDropdown ? 'block' : 'hidden'} far fa-times h-6 w-6`}></i>
                    </button>

                    {/* Desktop menu */}
                    <div className="hidden lg:flex lg:items-center lg:justify-between lg:flex-1 lg:ml-10">
                        <div className="flex gap-4 space-x-1">
                            <Link
                                href={route('roster.index')}
                                className="p-1 text-sm font-medium border-b border-transparent hover:border-white transition-colors"
                            >
                                <i className="fas fa-users mr-2"></i>
                                Roster
                            </Link>
                            {can?.accessLoot && (
                                <Link 
                                    href="/loot"
                                    className="p-1 text-sm font-medium border-b border-transparent hover:border-white transition-colors"
                                >
                                    <i className="fas fa-treasure-chest mr-2"></i>
                                    Loot Bias
                                </Link>
                            )}
                            <Link
                                href="https://discord.gg/regrowth"
                                className="p-1 text-sm font-medium border-b border-transparent hover:border-white transition-colors"
                            >
                                <i className="fab fa-discord mr-2"></i>
                                Discord
                            </Link>
                        </div>

                        <div className="flex items-center">
                            {user ? (
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <button className="flex items-center space-x-2 text-sm font-medium text-gray-300 hover:text-white transition-colors">
                                            <img
                                                src={user.avatar}
                                                alt={user.display_name}
                                                className="h-8 w-8 rounded-full"
                                            />
                                            <span>{user.display_name}</span>
                                            {user.highest_role && (
                                                <span className={`text-xs bg-discord-${user.highest_role ? user.highest_role.toLowerCase() : 'grey-800'} px-2 py-0.5 rounded`}>
                                                    {user.highest_role}
                                                </span>
                                            )}
                                            <i className="far fa-chevron-down text-xs"></i>
                                        </button>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        {/* <Dropdown.Link href={route('profile.edit')}>
                                            <i className="far fa-user-cog mr-2"></i>
                                            Account Settings
                                        </Dropdown.Link> */}
                                        {impersonating && (
                                            <Dropdown.Link href={route('auth.return-to-self')}>
                                                <i className="far fa-undo mr-2"></i>
                                                Return to my account
                                            </Dropdown.Link>
                                        )}
                                        {can?.accessDashboard && (
                                            <Dropdown.Link href={route('dashboard.index')}>
                                                <i className="far fa-cogs mr-2"></i>
                                                Officers&rsquo; Dashboard
                                            </Dropdown.Link>
                                        )}
                                        <Dropdown.Link
                                            href={route('logout')}
                                            method="post"
                                            as="button"
                                        >
                                            <i className="far fa-sign-out mr-2"></i>
                                            Logout
                                        </Dropdown.Link>
                                    </Dropdown.Content>
                                </Dropdown>
                            ) : (
                                <a
                                    href={route('login')}
                                    className="flex items-center space-x-2 px-4 py-2 bg-[#5865F2] hover:bg-[#4752C4] text-white rounded-md transition-colors"
                                >
                                    <i className="fab fa-discord"></i>
                                    <span>Login with Discord</span>
                                </a>
                            )}
                        </div>
                    </div>
                </nav>

                {/* Mobile menu */}
                <div
                    className={`${showingNavigationDropdown ? 'block' : 'hidden'} lg:hidden`}
                    id="mobile-menu"
                >
                    <div className="px-2 pt-2 pb-3 space-y-1">
                        <Link 
                            href={route('roster.index')}
                            className="block px-3 py-2 text-base font-medium text-gray-300 hover:text-white hover:bg-amber-700 rounded-md"
                        >
                            <i className="fas fa-users mr-2"></i>
                            Roster
                        </Link>
                        {can?.accessLoot && (
                            <Link 
                                href={route('loot.index')}
                                className="block px-3 py-2 text-base font-medium text-gray-300 hover:text-white hover:bg-amber-700 rounded-md"
                            >
                                <i className="fas fa-treasure-chest mr-2"></i>
                                Loot Bias
                            </Link>
                        )}
                        <Link
                            href="https://discord.gg/regrowth"
                            className="block px-3 py-2 text-base font-medium text-gray-300 hover:text-white hover:bg-amber-700 rounded-md"
                        >
                            <i className="fab fa-discord mr-2"></i>
                            Discord
                        </Link>
                    </div>

                    <div className="pt-4 pb-3 border-t border-amber-700">
                        {user ? (
                            <div className="px-4 space-y-2">
                                <div className="flex items-center space-x-3">
                                    <img
                                        src={user.avatar}
                                        alt={user.display_name}
                                        className="h-10 w-10 rounded-full"
                                    />
                                    <div>
                                        <div className="text-base font-medium text-white">
                                            {user.display_name}
                                        </div>
                                        {user.highest_role && (
                                            <div className="text-sm text-gray-400">
                                                {user.highest_role}
                                            </div>
                                        )}
                                    </div>
                                </div>
                                {/* <Link
                                    href={route('profile.edit')}
                                    className="block px-3 py-2 text-sm text-gray-300 hover:text-white hover:bg-amber-700 rounded-md"
                                >
                                    <i className="far fa-user-cog mr-2"></i>
                                    Account Settings
                                </Link> */}
                                {impersonating && (
                                    <Link 
                                        href={route('auth.return-to-self')}
                                        className="block w-full text-left px-3 py-2 text-sm text-gray-300 hover:text-white hover:bg-amber-700 rounded-md"
                                    >
                                        <i className="far fa-undo mr-2"></i>
                                        Return to my account
                                    </Link>
                                )}
                                {can?.accessDashboard && (
                                    <Link
                                        href={route('dashboard.index')}
                                        className="block w-full text-left px-3 py-2 text-sm text-gray-300 hover:text-white hover:bg-amber-700 rounded-md"
                                    >
                                        <i className="far fa-cogs mr-2"></i>
                                        Officers' Control Panel
                                    </Link>
                                )}
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    className="block w-full text-left px-3 py-2 text-sm text-gray-300 hover:text-white hover:bg-amber-700 rounded-md"
                                >
                                    <i className="far fa-sign-out mr-2"></i>
                                    Logout
                                </Link>
                            </div>
                        ) : (
                            <div className="px-4">
                                <Link
                                    href={route('login')}
                                    className="flex items-center justify-center space-x-2 px-4 py-2 bg-[#5865F2] hover:bg-[#4752C4] text-white rounded-md transition-colors"
                                >
                                    <i className="fab fa-discord"></i>
                                    <span>Login with Discord</span>
                                </Link>
                            </div>
                        )}
                    </div>
                </div>

                {/* Flash Messages */}
                <FlashMessage
                    type="error"
                    message={flashError}
                    onDismiss={() => setFlashError(null)}
                />
                <FlashMessage
                    type="success"
                    message={flashSuccess}
                    onDismiss={() => setFlashSuccess(null)}
                />

                <main>{children}</main>

                <footer className="mx-3 md:mx-5 py-5" id="footer">
                    <div className="container mx-auto">
                        {/* Footer Links */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 my-5">
                            {/* Column 1 - Legal & Account */}
                            <ul className="lg:col-start-2 space-y-2 text-center">
                                <li className="py-2 px-3 text-gray-300">
                                    <i className="far fa-copyright w-5 mr-2"></i>
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
                                        className="block px-3 py-2 text-gray-400 hover:text-white transition-colors"
                                    >
                                        <i className="fab fa-safari w-5 mr-2"></i>
                                        A Fizzywigs Production
                                    </a>
                                </li>
                            </ul>
                        </div>

                        {/* Logos Section */}
                        <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6 my-5">
                            <div className="lg:col-start-2 text-center">
                                <Link href="/" title="Regrowth">
                                    <img
                                        src="/images/guild_emblem.webp"
                                        alt="Guild Emblem"
                                        className="inline-block max-h-36 ml-4"
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
                            <p className="text-gray-500 text-sm">
                                Disclaimer: Classic is a trademark, and World of Warcraft and Warcraft are trademarks or registered trademarks of Blizzard Entertainment, Inc., in the U.S. and/or other countries. All related materials, logos, and images are copyright &copy; Blizzard Entertainment, Inc. Regrowth is in no way associated with or endorsed by Blizzard Entertainment.
                            </p>
                        </div>
                    </div>
                </footer>
            </div>
        </>
    );
}
