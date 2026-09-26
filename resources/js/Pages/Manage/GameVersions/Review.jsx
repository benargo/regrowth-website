import { Link } from "@inertiajs/react";
import ReviewSection, { selectedOptions } from "@/Components/Datasets/ReviewSection";
import SetupNavigation from "@/Components/Datasets/SetupNavigation";
import Icon from "@/Components/FontAwesome/Icon";
import { linkButtonClassName } from "@/Components/FormControls";
import SetupSteps from "@/Components/GameVersions/SetupSteps";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import formatDate from "@/Helpers/FormatDate";
import Master from "@/Layouts/Master";

/**
 * For each setup step, the lists of linked records to summarise. Each item is
 * a key plus a primary and optional secondary line, and an optional icon URL.
 */
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
                primary: phase.label,
                secondary: phase.description,
            })),
        },
        {
            heading: "Raids",
            items: selectedOptions(relationships.phases).flatMap((phase) =>
                phase.raids.map((raid) => ({
                    id: raid.id,
                    primary: raid.name,
                    secondary: [raid.difficulty, phase.label].filter(Boolean).join(" · "),
                })),
            ),
        },
    ],
};

function capitalise(value) {
    return value ? value.charAt(0).toUpperCase() + value.slice(1) : null;
}

/**
 * The details step's [label, value] pairs.
 */
function gameVersionDetails(gameVersion) {
    return [
        ["Title", gameVersion.title],
        ["Release date", gameVersion.release_date ? formatDate(gameVersion.release_date).long : null],
        ["Theme", capitalise(gameVersion.theme)],
        ["Realm", gameVersion.realm],
        ["Faction", capitalise(gameVersion.faction)],
        ["Blizzard API namespace", capitalise(gameVersion.blizzard_namespace)],
        ["Warcraft Logs guild ID", gameVersion.warcraftlogs_guild],
        ["Warcraft Logs expansion ID", gameVersion.warcraftlogs_expansion],
    ];
}

export default function Review({ gameVersion, steps, relationships }) {
    const lastStep = steps[steps.length - 1];

    return (
        <Master title={`Review ${gameVersion.title}`}>
            <SharedHeader backgroundClass={gameVersion.banner_class} title={`Review ${gameVersion.title}`} />

            <SetupSteps gameVersion={gameVersion} steps={steps} currentStep="review" />

            <PageContainer>
                <div className="mx-auto flex max-w-5xl flex-col gap-8">
                    <p className="text-secondary-300 max-w-prose">
                        Check the details and linked records below. Use Edit to go back to a step; saving it brings you
                        back here.
                    </p>

                    <ReviewSection
                        id="details"
                        title="Details"
                        editHref={`${route("management.game-versions.edit", { gameVersion: gameVersion.id, review: 1 })}#details`}
                        editLabel="Edit details"
                    >
                        <ReviewSection.Details details={gameVersionDetails(gameVersion)} />
                    </ReviewSection>

                    {steps.map((step) => {
                        const summarise = STEP_SUMMARIES[step.value];

                        if (!summarise) {
                            throw new Error(`The "${step.value}" step has no summary on the review page.`);
                        }

                        return (
                            <ReviewSection
                                key={step.value}
                                id={step.value}
                                title={step.label}
                                editHref={route("management.game-versions.setup", {
                                    gameVersion: gameVersion.id,
                                    step: step.value,
                                    review: 1,
                                })}
                                editLabel={`Edit ${step.label.toLowerCase()}`}
                            >
                                {summarise(relationships).map((list, index) => (
                                    <ReviewSection.Records
                                        key={list.heading ?? index}
                                        heading={list.heading}
                                        items={list.items}
                                    />
                                ))}
                            </ReviewSection>
                        );
                    })}

                    <SetupNavigation
                        backHref={route("management.game-versions.setup", [gameVersion.id, lastStep.value])}
                        backLabel={`Back to ${lastStep.label.toLowerCase()}`}
                    >
                        <Link href={route("management.game-versions.index")} className={linkButtonClassName}>
                            <Icon icon="check" />
                            Finish
                        </Link>
                    </SetupNavigation>
                </div>
            </PageContainer>
        </Master>
    );
}
