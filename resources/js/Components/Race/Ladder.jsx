import { Fragment } from "react";
import { Link } from "@inertiajs/react";
import CharacterPortrait from "@/Components/Characters/CharacterPortrait";
import groupByLevel from "@/Helpers/groupByLevel";

/**
 * The race standings as a ladder: one row per level that has racers, highest
 * first, each listing its racers as portrait and name chips linking to their
 * character pages. `racers` are
 * expected highest level first. The empty levels between `leaderLevel` and
 * `targetLevel` fold into a single "{n} levels still to climb" row, numbered
 * with the target level when nobody has reached it yet.
 */
export default function Ladder({ racers, leaderLevel, targetLevel = 60 }) {
    const rungs = groupByLevel(racers);
    const levelsToClimb = leaderLevel === null ? 0 : targetLevel - leaderLevel;
    const hasFinishers = rungs[0]?.level >= targetLevel;

    const climbRow =
        levelsToClimb > 1 || (levelsToClimb === 1 && !hasFinishers) ? (
            <li className="grid grid-cols-[2.75rem_1fr] gap-3 py-2 text-sm">
                <span className="text-camel-300 text-right font-serif text-xl leading-none">
                    {hasFinishers ? "" : targetLevel}
                </span>
                <span className="border-camel-800 text-camel-500 border-l-2 border-dotted pl-3">
                    {levelsToClimb} {levelsToClimb === 1 ? "level" : "levels"} still to climb
                </span>
            </li>
        ) : null;

    return (
        <ol className="text-base">
            {!hasFinishers && climbRow}
            {rungs.map((rung) => (
                <Fragment key={rung.level}>
                    {hasFinishers && rung.level === leaderLevel && climbRow}
                    <li className="grid grid-cols-[2.75rem_1fr] items-center gap-3 border-t border-white/5 py-2">
                        <span className="text-camel-300 text-right font-serif text-xl leading-none tabular-nums">
                            <span className="sr-only">Level </span>
                            {rung.level}
                        </span>
                        <ul className="flex flex-wrap gap-x-3.5 gap-y-2">
                            {rung.racers.map((racer) => (
                                <li key={racer.id}>
                                    <Link
                                        href={route("characters.show", { character: racer.id, slug: racer.slug })}
                                        className="text-camel-300 hover:text-camel-200 focus-visible:outline-camel-400 flex items-center gap-2 rounded-sm whitespace-nowrap underline-offset-2 transition-colors hover:underline focus-visible:outline-2 focus-visible:outline-offset-2"
                                    >
                                        <CharacterPortrait character={racer} className="size-7" />
                                        {racer.full_name}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </li>
                </Fragment>
            ))}
        </ol>
    );
}
