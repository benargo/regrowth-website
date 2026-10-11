import { router } from "@inertiajs/react";
import TabNav from "@/Components/TabNav";

/**
 * Tabs for switching between game versions. A switch revisits the current page
 * with `?game_version=`, which the server remembers as the user's current
 * version before redirecting back to the clean URL. Page state is preserved,
 * so per-version components key themselves on the slug.
 *
 * Renders nothing when there is at most one version to choose from.
 */
export default function GameVersionSelector({ gameVersions, currentGameVersion, indicator }) {
    if (gameVersions.length <= 1) {
        return null;
    }

    return (
        <TabNav
            currentTab={currentGameVersion?.slug}
            tabs={gameVersions.map((gameVersion) => ({
                name: gameVersion.slug,
                label: gameVersion.title,
                indicator: indicator?.(gameVersion),
                // A synchronous visit, unlike router.reload(), cancels an earlier switch still in
                // flight, so rapid clicks can't land out of order.
                onClick: () =>
                    router.get(
                        window.location.href,
                        { game_version: gameVersion.slug },
                        { preserveState: true, preserveScroll: true, replace: true },
                    ),
            }))}
        />
    );
}
