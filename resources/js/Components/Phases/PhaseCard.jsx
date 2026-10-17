import { useState } from "react";
import RecordCard, { usageSummary } from "@/Components/Datasets/RecordCard";
import PhaseStartDate from "@/Components/Phases/PhaseStartDate";

const USAGE_LABELS = [
    ["raids_count", "raid", "raids"],
    ["reports_count", "report", "reports"],
];

/**
 * One raid to paint the card's banner: picked at random from the phase's raids
 * with artwork, or from all of them when none has artwork. Null without raids.
 */
function pickBannerRaid(raids) {
    const withArtwork = raids.filter((raid) => raid.background);
    const pool = withArtwork.length > 0 ? withArtwork : raids;

    return pool.length > 0 ? pool[Math.floor(Math.random() * pool.length)] : null;
}

/**
 * A phase's card on the Index page, under a banner of one of its raids.
 */
export default function PhaseCard({ phase, dragHandle = null, onDelete, headingLevel = 3 }) {
    const raids = Object.values(phase.raids ?? {});
    const [bannerRaid] = useState(() => pickBannerRaid(raids));

    return (
        <RecordCard
            title={phase.name}
            subtitle={phase.description}
            headerColor={bannerRaid?.color}
            headerClassName={bannerRaid ? (bannerRaid.background ?? "") : "bg-officer-meeting"}
            badge={phase.is_current ? "Current phase" : null}
            handle={dragHandle}
            headingLevel={headingLevel}
            details={[
                ["Starts", <PhaseStartDate startDate={phase.start_date} />],
                ["Raids", raids.length > 0 ? raids.map((raid) => raid.name).join(", ") : "None yet"],
            ]}
            usage={usageSummary(phase, USAGE_LABELS)}
            links={phase.links}
            onDelete={onDelete}
        />
    );
}
