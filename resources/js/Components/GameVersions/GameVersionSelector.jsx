import { router } from "@inertiajs/react";
import TabNav from "@/Components/TabNav";

/**
 * Tabs for switching between game versions. A switch reloads the current page
 * with `?game_version=`, which the server remembers as the user's current
 * version in that same request. The address bar keeps its clean URL, and page
 * state is preserved, so per-version components key themselves on the slug.
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
                onClick: () => router.reload({ data: { game_version: gameVersion.slug }, preserveUrl: true }),
            }))}
        />
    );
}
