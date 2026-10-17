import RecordCard, { usageSummary } from "@/Components/Datasets/RecordCard";
import PhaseStartDate from "@/Components/Phases/PhaseStartDate";

const USAGE_LABELS = [
    ["raids_count", "raid", "raids"],
    ["reports_count", "report", "reports"],
];

/** A random offset, drawn once per page load, that shuffles the banner picks. */
const BANNER_SEED = Math.floor(Math.random() * 1000);

/**
 * One raid to paint the phase's banner: picked from its raids with artwork, or
 * from all of them when none has artwork, the same raid for a phase on every
 * render until the page reloads. Null without raids.
 */
function pickBannerRaid(phaseId, raids) {
    const withArtwork = raids.filter((raid) => raid.background);
    const pool = withArtwork.length > 0 ? withArtwork : raids;

    return pool.length > 0 ? pool[(phaseId + BANNER_SEED) % pool.length] : null;
}

/**
 * A phase's card on the Index page, under a banner of one of its raids.
 */
export default function PhaseCard({ phase, dragHandle = null, onDelete }) {
    const raids = Object.values(phase.raids ?? {});
    const bannerRaid = pickBannerRaid(phase.id, raids);

    return (
        <RecordCard
            title={phase.name}
            subtitle={phase.description}
            headerColor={bannerRaid?.color}
            headerClassName={bannerRaid ? (bannerRaid.background ?? "") : "bg-officer-meeting"}
            badge={phase.is_current ? "Current phase" : null}
            handle={dragHandle}
            headingLevel={3}
            hasBanner
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
