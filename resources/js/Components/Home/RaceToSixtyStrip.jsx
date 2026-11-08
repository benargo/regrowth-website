import { Link, router } from "@inertiajs/react";
import ExperienceBar from "@/Components/Race/ExperienceBar";
import Medal from "@/Components/Race/Medal";
import useRaceChannel from "@/Hooks/useRaceChannel";

const LINK_CLASSES =
    "text-camel-300 decoration-camel-600 hover:text-camel-100 focus-visible:outline-camel-300 font-serif whitespace-nowrap underline underline-offset-4 focus-visible:outline-2 focus-visible:outline-offset-2";

/**
 * The Race to Level 60 band at the foot of the homepage's first screen.
 *
 * While the race is running or closing it shows the leader and an experience
 * bar of the pack, with placed characters at the target level, and links to
 * the race page. Once finished it names every medallist and links to the
 * results. Renders nothing when `race` is null. Reloads `race` when a
 * broadcast reports changed standings.
 */
export default function RaceToSixtyStrip({ race }) {
    useRaceChannel(({ hasChanges }) => {
        if (hasChanges) {
            router.reload({ only: ["race"] });
        }
    });

    if (!race) {
        return null;
    }

    const placements = race.placements.data;

    return (
        <section
            aria-labelledby="race-to-sixty-strip-heading"
            className="border-camel-600 from-forever-800 to-forever-900 border-b-8 bg-linear-to-b"
        >
            {race.state === "finished" ? (
                <FinishedStrip placements={placements} />
            ) : (
                <RunningStrip race={race} placements={placements} />
            )}
        </section>
    );
}

/**
 * The strip while the race is running or closing.
 */
function RunningStrip({ race, placements }) {
    const leader = race.leader?.data ?? null;
    const finishers = placements.map((placement) => ({
        ...placement.character,
        level: race.target_level,
    }));

    return (
        <div className="container mx-auto px-4 py-5 md:py-6">
            <div className="flex flex-wrap items-center justify-between gap-x-6 gap-y-2">
                <div className="flex items-center gap-3">
                    <img src="/images/icon_camelot.webp" alt="" aria-hidden="true" className="w-8 shrink-0 md:w-10" />
                    <h2 id="race-to-sixty-strip-heading" className="text-camel-400 font-serif text-lg md:text-xl">
                        The Race to Level {race.target_level}
                    </h2>
                </div>
                <p className="text-camel-500 text-sm">
                    {leader && (
                        <>
                            <b className="text-camel-300 font-semibold">{leader.name}</b> leads at level {leader.level}
                            <span aria-hidden="true"> · </span>
                        </>
                    )}
                    <Link href={route("race.index")} className={LINK_CLASSES}>
                        Follow the race
                    </Link>
                </p>
            </div>

            <div className="mt-4" aria-hidden="true">
                <ExperienceBar
                    racers={[...finishers, ...race.pack.data]}
                    fillLevel={leader?.level ?? 0}
                    targetLevel={race.target_level}
                    focusRacerId={leader?.id ?? null}
                />
            </div>

            <p className="sr-only" aria-live="polite">
                {leader
                    ? `${leader.name} leads the race to level ${race.target_level} at level ${leader.level}.`
                    : `The race to level ${race.target_level} has no leader yet.`}
            </p>
        </div>
    );
}

/**
 * The strip once the race is finished, listing every medallist; tied
 * characters each get their own entry, keyed by placement id.
 */
function FinishedStrip({ placements }) {
    const medallists = placements.filter((placement) => placement.medal !== null);

    return (
        <div className="container mx-auto flex flex-wrap items-center gap-x-6 gap-y-3 px-4 py-5 md:py-6">
            <div className="flex items-center gap-3">
                <img src="/images/icon_camelot.webp" alt="" aria-hidden="true" className="w-8 shrink-0 md:w-10" />
                <h2 id="race-to-sixty-strip-heading" className="text-camel-400 font-serif text-lg md:text-xl">
                    The Race to Level 60 is won
                </h2>
            </div>
            <ol className="flex flex-wrap items-center gap-x-5 gap-y-2">
                {medallists.map((placement) => (
                    <li key={placement.id} className="text-camel-300 flex items-center gap-2 text-sm">
                        <Medal medal={placement.medal} size="sm" />
                        {placement.character.name}
                    </li>
                ))}
            </ol>
            <Link href={route("race.index")} className={`${LINK_CLASSES} md:ml-auto`}>
                See the results
            </Link>
        </div>
    );
}
