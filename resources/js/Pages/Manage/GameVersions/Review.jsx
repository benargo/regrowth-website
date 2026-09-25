import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import { linkClassName } from "@/Components/FormControls";
import SetupSteps from "@/Components/GameVersions/SetupSteps";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import formatDate from "@/Helpers/FormatDate";
import Master from "@/Layouts/Master";

const buttonClassName =
    "bg-ink-800 hover:bg-ink-900 focus-visible:outline-ink-400 inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold text-white focus-visible:outline-2 focus-visible:outline-offset-2";

/**
 * The records a relationship group links to this game version, in option order.
 */
function selected(group) {
    return group.options.filter((option) => group.selected_ids.includes(option.id));
}

/**
 * For each setup step, the lists of linked records to summarise. Each item is
 * a key plus a primary and optional secondary line, and an optional icon URL.
 */
const STEP_SUMMARIES = {
    "races-and-classes": (relationships) => [
        {
            heading: "Races",
            items: selected(relationships.playable_races).map((race) => ({ id: race.id, primary: race.name })),
        },
        {
            heading: "Classes",
            items: selected(relationships.playable_classes).map((playableClass) => ({
                id: playableClass.id,
                primary: playableClass.name,
                icon: playableClass.icon_url,
            })),
        },
    ],
    phases: (relationships) => [
        {
            items: selected(relationships.phases).map((phase) => ({
                id: phase.id,
                primary: phase.label,
                secondary: phase.description,
            })),
        },
        {
            heading: "Raids",
            items: selected(relationships.phases).flatMap((phase) =>
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

function ReviewSection({ id, title, editHref, editLabel, children }) {
    return (
        <section aria-labelledby={`${id}-heading`} className="border-ink-600/40 flex flex-col gap-4 border-t pt-6">
            <header className="flex flex-wrap items-center justify-between gap-4">
                <h2 id={`${id}-heading`} className="font-serif text-2xl text-white">
                    {title}
                </h2>
                <Link href={editHref} className={buttonClassName} aria-label={editLabel}>
                    <Icon icon="pen" style="light" />
                    Edit
                </Link>
            </header>
            {children}
        </section>
    );
}

function DetailsList({ gameVersion }) {
    const details = [
        ["Title", gameVersion.title],
        ["Release date", gameVersion.release_date ? formatDate(gameVersion.release_date).long : null],
        ["Theme", capitalise(gameVersion.theme)],
        ["Realm", gameVersion.realm],
        ["Faction", capitalise(gameVersion.faction)],
        ["Blizzard API namespace", capitalise(gameVersion.blizzard_namespace)],
        ["Warcraft Logs guild ID", gameVersion.warcraftlogs_guild],
        ["Warcraft Logs expansion ID", gameVersion.warcraftlogs_expansion],
    ];

    return (
        <dl className="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-[max-content_1fr]">
            {details.map(([label, value]) => (
                <div key={label} className="contents">
                    <dt className="text-secondary-300">{label}</dt>
                    <dd className={value ? "text-white" : "text-secondary-400 italic"}>{value || "Not set"}</dd>
                </div>
            ))}
        </dl>
    );
}

function RecordList({ heading, items }) {
    return (
        <div className="flex flex-col gap-2">
            {heading && <h3 className="text-secondary-300 text-sm font-semibold">{heading}</h3>}
            {items.length === 0 ? (
                <p className="text-secondary-400 text-sm italic">None linked.</p>
            ) : (
                <ul className="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                    {items.map((item) => (
                        <li key={item.id} className="flex items-center gap-2">
                            {item.icon && <img src={item.icon} alt="" className="h-4 w-4 rounded-xs" />}
                            <div className="flex flex-col">
                                <span className="text-white">{item.primary}</span>
                                {item.secondary && <span className="text-secondary-400">{item.secondary}</span>}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
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
                        <DetailsList gameVersion={gameVersion} />
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
                                    <RecordList key={list.heading ?? index} heading={list.heading} items={list.items} />
                                ))}
                            </ReviewSection>
                        );
                    })}

                    <nav
                        aria-label="Setup navigation"
                        className="border-ink-600/40 flex flex-wrap items-center justify-between gap-4 border-t pt-6"
                    >
                        <Link
                            href={route("management.game-versions.setup", [gameVersion.id, lastStep.value])}
                            className={linkClassName}
                        >
                            {`Back to ${lastStep.label.toLowerCase()}`}
                        </Link>
                        <Link href={route("management.game-versions.index")} className={buttonClassName}>
                            <Icon icon="check" />
                            Finish
                        </Link>
                    </nav>
                </div>
            </PageContainer>
        </Master>
    );
}
