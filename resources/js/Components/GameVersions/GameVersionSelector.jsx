import { router } from "@inertiajs/react";
import TabNav from "@/Components/TabNav";
import modelColor from "@/Helpers/ModelColor";

/**
 * Tabs for switching between game versions. A switch revisits the current page
 * with `?game_version=`, preserving page state.
 *
 * The selected tab is drawn in its version's colour.
 *
 * With `href`, each tab is a link to `href(gameVersion)`, a full visit that drops the current page's state.
 *
 * Renders nothing when there is at most one version to choose from.
 */
export default function GameVersionSelector({ gameVersions, currentGameVersion, indicator, href }) {
    if (gameVersions.length <= 1) {
        return null;
    }

    return (
        <TabNav
            ariaLabel="Game version"
            currentTab={currentGameVersion?.slug}
            tabs={gameVersions.map((gameVersion) => {
                // A version without a colour falls back to TabNav's default selected style.
                const { className, style } = modelColor(gameVersion.color, ["text", "border"]);

                return {
                    name: gameVersion.slug,
                    label: gameVersion.title,
                    indicator: indicator?.(gameVersion),
                    activeClassName: className,
                    style,
                    ...(href
                        ? { href: href(gameVersion) }
                        : {
                            onClick: () =>
                                router.get(
                                    window.location.href,
                                    { game_version: gameVersion.slug },
                                    { preserveState: true, preserveScroll: true, replace: true },
                                ),
                          }),
                };
            })}
        />
    );
}
