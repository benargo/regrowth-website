import ReviewSummary from "@/Components/Datasets/ReviewSummary";
import SetupSteps from "@/Components/Datasets/SetupSteps";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import { toHex } from "@/Helpers/ModelColor";
import Master from "@/Layouts/Master";

/** Per-step summary builders, given the relationships and the raid. */
const STEP_SUMMARIES = {
    bosses: (relationships, raid) => [
        {
            items: (raid.bosses ?? []).map((boss, index) => ({ id: boss.id, primary: `${index + 1}. ${boss.name}` })),
        },
    ],
};

/** The details step's [label, value] pairs. */
function raidDetails(raid, bannerPositions) {
    const position = bannerPositions.find((option) => option.value === raid.banner_position)?.label ?? null;

    return [
        ["Name", raid.name],
        ["Difficulty", raid.difficulty],
        ["Phase", raid.phase?.name],
        ["Colour", toHex(raid.color)],
        ["Maximum players", raid.max_players?.toString()],
        ["Maximum loot councillors", raid.max_loot_councillors?.toString()],
        ["Banner", raid.banner_url ? "Uploaded" : null],
        ["Banner position", position],
    ];
}

export default function Review({ raid: { data: raid }, options, steps, routes }) {
    return (
        <Master title={`Review ${raid.name}`}>
            <SharedHeader
                color={raid.color}
                image={raid.banner_url}
                imagePosition={raid.banner_position}
                backgroundClass="bg-officer-meeting"
                title={`Review ${raid.name}`}
            />

            <SetupSteps steps={steps} currentStep="review" routes={routes} />

            <PageContainer>
                <ReviewSummary
                    routes={routes}
                    steps={steps}
                    details={raidDetails(raid, options.banner_positions)}
                    record={raid}
                    summaries={STEP_SUMMARIES}
                    relationships={{}}
                />
            </PageContainer>
        </Master>
    );
}
