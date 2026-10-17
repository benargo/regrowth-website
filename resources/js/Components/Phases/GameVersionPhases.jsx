import { useMemo } from "react";
import { Link } from "@inertiajs/react";
import { focusRing } from "@/Components/Datasets/RecordCard";
import Icon from "@/Components/FontAwesome/Icon";
import PhaseCard from "@/Components/Phases/PhaseCard";
import UnscheduledPhaseOrder from "@/Components/Phases/UnscheduledPhaseOrder";
import modelColor from "@/Helpers/ModelColor";

/**
 * One game version's phases on the Index page: dated phases on a timeline,
 * then unscheduled phases in a list officers drag into order. A group with no
 * game version holds the phases not yet linked to one.
 */
export default function GameVersionPhases({ group, reorderUrl, onDelete }) {
    const gameVersion = group.game_version?.data ?? null;
    const phases = group.phases.data;
    const scheduled = phases.filter((phase) => phase.start_date);
    const unscheduled = useMemo(() => phases.filter((phase) => !phase.start_date), [phases]);
    const title = gameVersion?.title ?? "No game version";
    const headingId = `phases-${gameVersion?.id ?? "none"}`;
    const rule = modelColor(gameVersion?.color, ["bg"]);
    const createHref = route("management.phases.create", gameVersion ? { game_version: gameVersion.id } : {});

    return (
        <section aria-labelledby={headingId} className="flex flex-col gap-6">
            <header className="flex flex-col gap-2">
                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2">
                    <h2 id={headingId} className="font-serif text-3xl text-white">
                        {title}
                    </h2>
                    <Link href={createHref} className={`text-sm text-gray-300 underline hover:text-white ${focusRing}`}>
                        Add a phase<span className="sr-only"> to {gameVersion?.title ?? "no game version"}</span>
                    </Link>
                </div>
                <div
                    aria-hidden="true"
                    className={`h-0.5 w-full ${rule.className || "bg-amber-600/60"}`}
                    style={rule.style}
                />
                {gameVersion?.release_date && (
                    <p className="text-sm text-gray-300">
                        Released{" "}
                        <time dateTime={gameVersion.release_date}>
                            {new Date(`${gameVersion.release_date}T00:00:00`).toLocaleDateString("en-GB", {
                                day: "numeric",
                                month: "long",
                                year: "numeric",
                            })}
                        </time>
                    </p>
                )}
            </header>

            {phases.length === 0 && <p className="text-gray-300">No phases yet. Add the first one.</p>}

            {scheduled.length > 0 && (
                <ol
                    aria-label={`${title} phases by start date`}
                    className="flex flex-col gap-6 border-l-2 border-amber-600/60 pl-6"
                >
                    {scheduled.map((phase) => (
                        <li key={phase.id} className="relative">
                            <span
                                aria-hidden="true"
                                className={`absolute top-6 -left-[33px] h-4 w-4 rounded-full border-2 ${
                                    phase.is_current
                                        ? `border-white ${rule.className || "bg-amber-400"}`
                                        : phase.has_started
                                          ? "border-amber-400 bg-amber-400"
                                          : "bg-brown-900 border-amber-400"
                                }`}
                                style={phase.is_current ? rule.style : undefined}
                            />
                            <PhaseCard phase={phase} onDelete={() => onDelete(phase)} />
                        </li>
                    ))}
                </ol>
            )}

            {unscheduled.length > 0 && (
                <div className="flex flex-col gap-3">
                    <h3 className="font-serif text-xl text-white">Not scheduled yet</h3>
                    <p className="text-sm text-gray-300">
                        Drag these into the order you expect them to arrive. Once a phase has a start date, it moves to
                        the timeline above.
                    </p>
                    <UnscheduledPhaseOrder
                        phases={unscheduled}
                        url={reorderUrl}
                        field="phase_ids"
                        data={{ game_version_id: gameVersion?.id ?? null }}
                        label={`Unscheduled ${gameVersion?.title ?? "unlinked"} phases`}
                        className="flex flex-col gap-4"
                        renderPhase={(phase, dragHandle) => (
                            <PhaseCard phase={phase} dragHandle={dragHandle} onDelete={() => onDelete(phase)} />
                        )}
                    />
                </div>
            )}
        </section>
    );
}
