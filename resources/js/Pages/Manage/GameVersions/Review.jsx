import { selectedOptions } from "@/Components/Datasets/ReviewSection";
import ReviewSummary from "@/Components/Datasets/ReviewSummary";
import SetupSteps from "@/Components/Datasets/SetupSteps";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import formatDate from "@/Helpers/FormatDate";
import Master from "@/Layouts/Master";

/** Per-step summary builders for the linked records lists. */
const STEP_SUMMARIES = {
    "races-and-classes": (relationships) => [
        {
            heading: "Races",
            items: selectedOptions(relationships.playable_races).map((race) => ({ id: race.id, primary: race.name })),
        },
        {
            heading: "Classes",
            items: selectedOptions(relationships.playable_classes).map((playableClass) => ({
                id: playableClass.id,
                primary: playableClass.name,
                icon: playableClass.icon_url,
            })),
        },
    ],
    phases: (relationships) => [
        {
            items: selectedOptions(relationships.phases).map((phase) => ({
                id: phase.id,
                primary: phase.name,
                secondary: phase.description,
            })),
        },
        {
            heading: "Raids",
            items: selectedOptions(relationships.phases).flatMap((phase) =>
                phase.raids.map((raid) => ({
                    id: raid.id,
                    primary: raid.name,
                    secondary: [raid.difficulty, phase.name].filter(Boolean).join(" · "),
                })),
            ),
        },
    ],
    "guild-ranks": (relationships) => [
        {
            items: relationships.guild_ranks.data.map((rank) => ({
                id: rank.id,
                primary: `${rank.sort_order}. ${rank.name}`,
                secondary: rank.count_attendance ? "Counts attendance" : "Doesn't count attendance",
            })),
        },
    ],
};

function capitalise(value) {
    return value ? value.charAt(0).toUpperCase() + value.slice(1) : null;
}

/** The details step's [label, value] pairs. */
function gameVersionDetails(gameVersion) {
    return [
        ["Title", gameVersion.title],
        ["Slug", gameVersion.slug],
        ["Release date", gameVersion.release_date ? formatDate(gameVersion.release_date).long : null],
        ["Theme", capitalise(gameVersion.theme)],
        ["Realm", gameVersion.realm],
        ["Guild name", gameVersion.guild_name],
        ["Characters have surnames", gameVersion.uses_surnames ? "Yes" : "No"],
        ["Faction", capitalise(gameVersion.faction)],
        ["Blizzard API namespace", capitalise(gameVersion.blizzard.namespace)],
        ["Warcraft Logs guild ID", gameVersion.warcraftlogs.guild],
        ["Warcraft Logs namespace", gameVersion.warcraftlogs.namespace.label],
    ];
}

export default function Review({ gameVersion, steps, relationships, routes }) {
    return (
        <Master title={`Review ${gameVersion.title}`}>
            <SharedHeader backgroundClass={gameVersion.banner_class} title={`Review ${gameVersion.title}`} />

            <SetupSteps steps={steps} currentStep="review" routes={routes} />

            <PageContainer>
                <ReviewSummary
                    routes={routes}
                    steps={steps}
                    details={gameVersionDetails(gameVersion)}
                    summaries={STEP_SUMMARIES}
                    relationships={relationships}
                />
            </PageContainer>
        </Master>
    );
}
