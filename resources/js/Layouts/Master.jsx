import { useState, useEffect } from 'react';
import { Link, Head, usePage } from '@inertiajs/react';
import Dropdown from '@/Components/Dropdown';

export default function Master({ title, children }) {
    const { auth, canAccessControlPanel } = usePage().props;
    const user = auth?.user;

    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);

    useEffect(() => {
        document.body.classList.add('bg-white', 'bg-brown-texture');
        return () => {
            document.body.classList.remove('bg-white', 'bg-brown-texture');
        };
    }, []);

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
                        <div className="flex space-x-1">
                            {/* Navigation items go here */}
                            <Link
                                href="/discord"
                                className="p-1 text-sm font-medium border-b border-transparent hover:border-white transition-colors"
                            >
                                <i className="fab fa-discord mr-2"></i>
                                Discord
                            </Link>
                        </div>

                        <div className="flex items-center">
                            {/* User Account Dropdown */}
                        </div>
                    </div>
                </nav>

                {/* Mobile menu */}
                <div
                    className={`${showingNavigationDropdown ? 'block' : 'hidden'} lg:hidden`}
                    id="mobile-menu"
                >
                    <div className="px-2 pt-2 pb-3 space-y-1">
                        {/* Navigation items go here */}
                        <Link
                            href="/discord"
                            className="block px-3 py-2 text-base font-medium text-gray-300 hover:text-white hover:bg-gray-700 rounded-md"
                        >
                            <i className="fab fa-discord mr-2"></i>
                            Discord
                        </Link>
                    </div>

                    <div className="pt-4 pb-3 border-t border-gray-700">
                        {user ? (
                            <div className="px-4 space-y-2">
                                <div className="text-base font-medium text-white">
                                    {user.nickname || user.name}
                                </div>
                                <Link
                                    href={route('profile.edit')}
                                    className="block px-3 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-700 rounded-md"
                                >
                                    <i className="far fa-user-cog mr-2"></i>
                                    Account Settings
                                </Link>
                                {canAccessControlPanel && (
                                    <Link
                                        href={route('control_panel.index')}
                                        className="block px-3 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-700 rounded-md"
                                    >
                                        <i className="far fa-cogs mr-2"></i>
                                        Officers' Control Panel
                                    </Link>
                                )}
                                <Link
                                    href={route('logout')}
                                    method="post"
                                    as="button"
                                    className="block w-full text-left px-3 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-700 rounded-md"
                                >
                                    <i className="far fa-sign-out mr-2"></i>
                                    Logout
                                </Link>
                            </div>
                        ) : (
                            <div className="px-4">
                                <Link
                                    className="block px-3 py-2 text-sm text-gray-300 hover:text-white hover:bg-gray-700 rounded-md"
                                    href={route('login')}
                                >
                                    <i className="far fa-sign-in mr-2"></i>
                                    Login
                                </Link>
                            </div>
                        )}
                    </div>
                </div>

                <main>{children}</main>

                <footer className="mx-3 md:mx-5 py-5" id="footer">
                    <div className="container mx-auto">
                        {/* Footer Links */}
                        <div className="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-6 gap-6 my-5">
                            {/* Column 1 - Legal & Account */}
                            <ul className="md:col-start-2 lg:col-start-3 space-y-2 text-center">
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
                                        Website by Ben Argo
                                    </a>
                                </li>
                            </ul>
                        </div>

                        {/* Logos Section */}
                        <div className="grid grid-cols-1 md:grid-cols-4 lg:grid-cols-6 gap-6 my-5">
                            <div className="md:col-start-2 lg:col-start-3 text-center">
                                <Link href="/" title="Regrowth">
                                    <img
                                        src="/images/guild_emblem.webp"
                                        alt="Guild Emblem"
                                        className="inline-block max-h-36 ml-4"
                                    />
                                </Link>
                            </div>
                            <div className="text-center">
                                <a
                                    href="https://worldofwarcraft.blizzard.com/en-gb/wowclassic"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="World of Warcraft Classic"
                                >
                                    <img
                                        src="/images/logo_tbcclassic.webp"
                                        alt="World of Warcraft Classic logo"
                                        className="inline-block max-h-36"
                                    />
                                    <span className="sr-only">World of Warcraft: Classic</span>
                                </a>
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
