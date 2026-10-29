import RecordCard, { usageSummary } from "@/Components/Datasets/RecordCard";

const USAGE_LABELS = [
    ["items_count", "item", "items"],
    ["upcoming_events_count", "upcoming event", "upcoming events"],
    ["upcoming_assignments_count", "upcoming assignment", "upcoming assignments"],
    ["upcoming_assignment_groups_count", "upcoming assignment group", "upcoming assignment groups"],
];

const LINK_LABELS = [
    ["past_events_count", "past event", "past events"],
    ["template_events_count", "event template", "event templates"],
];

const OWNED_LABELS = [["past_assignments_count", "past assignment", "past assignments"]];

const LIST_FORMAT = new Intl.ListFormat("en-GB", { type: "conjunction" });

/**
 * The sentence the delete confirmation shows about the past links, event
 * templates and past assignments that go with the boss, or null when there
 * are none.
 */
export function bossDeleteWarning(boss) {
    const linked = usageSummary(boss, LINK_LABELS);
    const owned = usageSummary(boss, OWNED_LABELS);
    const parts = [
        ...(linked.length > 0 ? [`removes it from ${LIST_FORMAT.format(linked)}`] : []),
        ...(owned.length > 0 ? [`deletes its ${LIST_FORMAT.format(owned)}`] : []),
    ];

    return parts.length > 0 ? `Deleting ${boss.name} also ${parts.join(" and ")}.` : null;
}

/**
 * A boss's card on the Index page.
 */
export default function BossCard({ boss, raidName, onDelete }) {
    const deleteWarning = bossDeleteWarning(boss);

    return (
        <RecordCard
            title={boss.name}
            subtitle={raidName}
            headerClassName="bg-officer-meeting"
            badge={boss.meta?.has_strategy ? "Strategy" : null}
            headingLevel={4}
            details={[["Encounter order", boss.sort_order]]}
            usage={usageSummary(boss, USAGE_LABELS)}
            links={boss.links}
            deleteWarning={deleteWarning}
            onDelete={(warning) => onDelete(boss, warning)}
        />
    );
}
