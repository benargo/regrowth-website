import RecordCard, { usageSummary } from "@/Components/Datasets/RecordCard";

const USAGE_LABELS = [
    ["bosses_count", "boss", "bosses"],
    ["upcoming_events_count", "upcoming event", "upcoming events"],
];

const LINK_LABELS = [
    ["past_events_count", "past event", "past events"],
    ["template_events_count", "event template", "event templates"],
];

const LIST_FORMAT = new Intl.ListFormat("en-GB", { type: "conjunction" });

/**
 * The sentence naming what deleting the raid also removes: its links to past
 * events, event templates and items. Null when there are none.
 */
export function raidDeleteWarning(raid) {
    const linked = usageSummary(raid, LINK_LABELS);
    const [items] = usageSummary(raid, [["items_count", "item", "items"]]);
    const consequences = [
        ...(linked.length > 0 ? [`removes it from ${LIST_FORMAT.format(linked)}`] : []),
        ...(items ? [`unlinks ${items}`] : []),
    ];

    const separator = linked.length > 1 ? ", and " : " and ";

    return consequences.length > 0 ? `Deleting ${raid.name} also ${consequences.join(separator)}.` : null;
}

/**
 * "Phase 1 · Normal · 10 players", leaving out the parts that aren't set.
 */
function raidSubtitle(raid) {
    return [raid.phase?.name, raid.difficulty, raid.max_players ? `${raid.max_players} players` : null]
        .filter(Boolean)
        .join(" · ");
}

/**
 * A raid's card on the Index page, under its banner.
 */
export default function RaidCard({ raid, onDelete }) {
    return (
        <RecordCard
            title={raid.name}
            subtitle={raidSubtitle(raid)}
            headerColor={raid.color}
            headerImage={raid.banner_url}
            headerImagePosition={raid.banner_position}
            headerClassName={raid.banner_url || raid.color ? "" : "bg-officer-meeting"}
            headingLevel={3}
            hasBanner
            details={[
                ["Maximum loot councillors", raid.max_loot_councillors],
                ["Items", raid.items_count],
            ]}
            usage={usageSummary(raid, USAGE_LABELS)}
            links={raid.links}
            deleteWarning={raidDeleteWarning(raid)}
            onDelete={onDelete}
        />
    );
}
