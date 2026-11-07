import { useState, useEffect, useRef, useCallback } from "react";
import { Head, router, usePage } from "@inertiajs/react";
import FlashMessage from "@/Components/FlashMessage";
import { DesktopNavigation, MobileMenuToggle, MobileNavigation, NAV_CLASSES } from "@/Components/MainNavigation";
import { GAME_VERSION_SWITCH_ERROR_BAG } from "@/Hooks/useGameVersionSwitch";
import SearchPalette from "@/Components/Search/SearchPalette";
import Footer from "@/Layouts/Footer";
import cn from "@/Helpers/cn";

export default function Master({ title, isHomepage = false, children }) {
    const { props, flash: clientFlash } = usePage();
    const { flash, errors, navigation, gameVersions: { data: gameVersions = [] } = {} } = props;
    const classes = isHomepage ? NAV_CLASSES.forever : NAV_CLASSES.classic;

    const [showingNavigationDropdown, setShowingNavigationDropdown] = useState(false);
    const mobileMenuToggleRef = useRef(null);
    const [errorFlash, setErrorFlash] = useState({ message: flash?.error, key: 0 });
    const [successFlash, setSuccessFlash] = useState({ message: flash?.success, key: 0 });
    const [searchOpen, setSearchOpen] = useState(false);
    const closeMobileMenu = useCallback(() => setShowingNavigationDropdown(false), []);

    /** Show `message` in the error banner, remounting it so a repeated or dismissed message appears again. */
    const showError = (message) => setErrorFlash((previous) => ({ message, key: previous.key + 1 }));
    /** Show `message` in the success banner, remounting it so a repeated or dismissed message appears again. */
    const showSuccess = (message) => setSuccessFlash((previous) => ({ message, key: previous.key + 1 }));

    // Show the session-backed flash messages from every successful visit, including a repeat of the last one.
    useEffect(
        () =>
            router.on("success", (event) => {
                const { error, success } = event.detail.page.props.flash ?? {};

                showError(error);
                showSuccess(success);
            }),
        [],
    );

    // Show messages raised on the client with router.flash(), then clear them.
    useEffect(() => {
        if (!clientFlash?.error && !clientFlash?.success) {
            return;
        }

        if (clientFlash.error) {
            showError(clientFlash.error);
        }

        if (clientFlash.success) {
            showSuccess(clientFlash.success);
        }

        router.flash(() => ({}));
    }, [clientFlash]);

    // Surface game version switch validation errors as a flash error.
    const gameVersionSwitchError = errors?.[GAME_VERSION_SWITCH_ERROR_BAG]?.game_version;
    useEffect(() => {
        if (gameVersionSwitchError) {
            showError(gameVersionSwitchError);
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
                <DesktopNavigation
                    classes={classes}
                    mobileMenuToggle={
                        <MobileMenuToggle
                            classes={classes}
                            isOpen={showingNavigationDropdown}
                            onToggle={() => setShowingNavigationDropdown(!showingNavigationDropdown)}
                            toggleRef={mobileMenuToggleRef}
                        />
                    }
                    onSearch={() => setSearchOpen(true)}
                />

                <MobileNavigation
                    classes={classes}
                    isOpen={showingNavigationDropdown}
                    onClose={closeMobileMenu}
                    mobileMenuToggleRef={mobileMenuToggleRef}
                />

                <div inert={showingNavigationDropdown} className="contents">
                    {/* Flash Messages */}
                    <FlashMessage
                        key={`error-${errorFlash.key}`}
                        type="error"
                        message={errorFlash.message}
                        onDismiss={() => setErrorFlash((previous) => ({ ...previous, message: null }))}
                    />
                    <FlashMessage
                        key={`success-${successFlash.key}`}
                        type="success"
                        message={successFlash.message}
                        onDismiss={() => setSuccessFlash((previous) => ({ ...previous, message: null }))}
                    />

                    <main id="main-content" tabIndex={-1} className="flex flex-1 flex-col focus:outline-none">
                        {children}
                    </main>

                    <Footer
                        classes={classes}
                        warcraftLogsUrl={navigation?.warcraft_logs_url}
                        gameVersions={gameVersions}
                    />
                </div>
            </div>

            <SearchPalette open={searchOpen} onClose={() => setSearchOpen(false)} />
        </>
    );
}
