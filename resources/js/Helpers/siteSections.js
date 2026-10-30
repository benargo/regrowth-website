/**
 * The Raiding, Loot and Addons sections: their hub route, header background, the
 * client-only features they enable, and the items of their sub-navigation.
 * `features.raidScopedSearch` scopes the search palette to the raid page's raid.
 * Each item names the route it links to, the route patterns that mark it
 * current, the permission it needs (if any), and whether it appears as a card
 * on the hub.
 */
export const SITE_SECTIONS = {
    raiding: {
        label: "Raiding",
        hubRouteName: "raiding.index",
        headerBackgroundClass: "bg-illidan",
        features: { raidScopedSearch: false },
        items: [
            {
                key: "raids",
                label: "Raids",
                icon: "dungeon",
                description: "This version's raids, their bosses and strategies.",
                routeName: "raiding.raids.index",
                currentPatterns: ["raiding.raids.*", "raiding.bosses.*"],
                isHubCard: true,
            },
            {
                key: "events",
                label: "Events",
                icon: "calendar-alt",
                description: "Upcoming raid nights and their plans, then past ones.",
                routeName: "raiding.events.index",
                currentPatterns: ["raiding.events.*", "raiding.plans.*"],
                isHubCard: true,
            },
            {
                key: "reports",
                label: "Reports",
                icon: "scroll",
                description: "Warcraft Logs reports from our raids.",
                routeName: "raiding.reports.index",
                currentPatterns: ["raiding.reports.*"],
                isHubCard: true,
            },
            {
                key: "attendance",
                label: "Attendance",
                icon: "chart-line",
                description: "Who's been raiding.",
                routeName: "raiding.attendance.dashboard",
                currentPatterns: ["raiding.attendance.*"],
                permission: "view-attendance",
            },
            {
                key: "absences",
                label: "Absences",
                icon: "calendar-times",
                description: "Planned time away.",
                routeName: "raiding.absences.index",
                currentPatterns: ["raiding.absences.*"],
                permission: "view-planned-absences",
            },
            {
                key: "handbook",
                label: "Handbook",
                icon: "book",
                description: "How we raid: expectations, schedule, teams, loot and more.",
                routeName: "raiding.handbook.index",
                currentPatterns: ["raiding.handbook.*"],
                permission: "view-handbook",
                isHubCard: true,
            },
        ],
    },
    loot: {
        label: "Loot",
        hubRouteName: "loot.index",
        headerBackgroundClass: "bg-vashj-and-kaelthas",
        features: { raidScopedSearch: true },
        items: [
            {
                key: "raids",
                label: "Items",
                icon: "dungeon",
                description: "Every raid's bosses, their loot and its priorities.",
                routeName: "loot.raids.index",
                currentPatterns: ["loot.raids.*", "loot.bosses.*", "loot.items.*"],
                isHubCard: true,
            },
            {
                key: "priorities",
                label: "Priorities",
                icon: "list-ol",
                description: "Where the heaviest demand for this version's loot sits.",
                routeName: "loot.priorities",
                currentPatterns: ["loot.priorities"],
                permission: "view-priorities-page",
                isHubCard: true,
            },
            {
                key: "comments",
                label: "Comments",
                icon: "comments",
                description: "Every discussion on this version's items.",
                routeName: "loot.comments",
                currentPatterns: ["loot.comments"],
                isHubCard: true,
            },
            {
                key: "addon",
                label: "In-game addon",
                icon: "puzzle-piece",
                description: "The Regrowth Loot Tool addon for loot council info in game.",
                routeName: "addons.loot-tool.index",
                currentPatterns: ["addons.loot-tool.index"],
            },
        ],
    },
    addons: {
        label: "Addons",
        hubRouteName: "addons.index",
        headerBackgroundClass: "bg-karazhan-chess",
        features: { raidScopedSearch: false },
        items: [
            {
                key: "loot-tool",
                label: "Loot Tool",
                icon: "toolbox",
                description: "Loot council info in your in-game tooltips.",
                routeName: "addons.loot-tool.index",
                currentPatterns: ["addons.loot-tool.*"],
                isHubCard: true,
            },
        ],
    },
};

/**
 * Whether the given section (which may be undefined) shows the named feature.
 */
export function sectionShows(section, feature) {
    return SITE_SECTIONS[section]?.features?.[feature] === true;
}

/**
 * The key of the section item whose route patterns match the current route, or null.
 */
export function currentSectionItemKey(section) {
    return (
        SITE_SECTIONS[section].items.find((item) => item.currentPatterns.some((pattern) => route().current(pattern)))
            ?.key ?? null
    );
}

/**
 * Breadcrumbs from the section hub through its Raids index, followed by the given trail.
 */
export function sectionCrumbs(section, trail) {
    return [
        { label: SITE_SECTIONS[section].label, href: route(SITE_SECTIONS[section].hubRouteName) },
        { label: "Raids", href: route(`${section}.raids.index`) },
        ...trail,
    ];
}
