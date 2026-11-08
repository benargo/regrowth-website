import { useMemo, useState } from "react";
import { router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import CharacterPortraitListener from "@/Components/Listeners/CharacterPortraitListener";
import CheckedAgo from "@/Components/Race/CheckedAgo";
import ClassToken from "@/Components/Race/ClassToken";
import ExperienceBar from "@/Components/Race/ExperienceBar";
import Ladder from "@/Components/Race/Ladder";
import Podium from "@/Components/Race/Podium";
import SharedHeader from "@/Components/SharedHeader";
import useRaceChannel from "@/Hooks/useRaceChannel";
import { formatDateTime, formatDayMonth } from "@/Helpers/serverTime";

/**
 * The later of two ISO timestamps, either of which may be null.
 */
function latestOf(first, second) {
    if (!first || !second) {
        return first ?? second ?? null;
    }

    return new Date(first) > new Date(second) ? first : second;
}

/**
 * Reload the portrait map.
 */
function reloadCharacterPortraits() {
    router.reload({ only: ["character_portraits"] });
}

/**
 * The Race to Level 60 page. While the race runs it shows the podium, the
 * grand experience bar and the ladder of every racer; once finished it shows
 * the results. Updates the checked time from broadcasts and reloads `race`
 * when they report changed standings. Reloads `characterPortraits` when a
 * racer's queued portrait arrives.
 */
export default function RaceIndex({ race, character_portraits: characterPortraits }) {
    const [broadcastCheckedAt, setBroadcastCheckedAt] = useState(null);

    useRaceChannel(({ checkedAt, hasChanges }) => {
        setBroadcastCheckedAt(checkedAt);

        if (hasChanges) {
            router.reload({ only: ["race"] });
        }
    });

    return (
        <Master title="The Race to Level 60" isHomepage>
            <SharedHeader
                title={`The Race to Level ${race?.target_level ?? 60}`}
                subtitle="World of Warcraft: Forever"
                backgroundClass="bg-the-barrens"
            />
            <div className="text-camel-400 flex-1 bg-[linear-gradient(var(--color-forever-800),var(--color-forever-900)_22rem)] pt-8 pb-16">
                <div className="mx-auto max-w-5xl px-4">
                    {!race ? (
                        <NoRace key="none" />
                    ) : race.state === "finished" ? (
                        <Results key="finished" race={race} />
                    ) : (
                        <Standings
                            key="standings"
                            race={race}
                            characterPortraits={characterPortraits}
                            checkedAt={latestOf(broadcastCheckedAt, race.checked_at)}
                        />
                    )}
                </div>
            </div>
        </Master>
    );
}

/**
 * The page when there is no Forever race.
 */
function NoRace() {
    return <p className="text-camel-400 text-base">There is no race to follow yet.</p>;
}

/**
 * The race while it is running or closing: header, podium, grand bar and ladder.
 * `characterPortraits` maps racer IDs to portrait URLs that override the racers' own.
 */
function Standings({ race, characterPortraits, checkedAt }) {
    const leader = race.leader?.data ?? null;
    const racers = useMemo(
        () =>
            race.racers.data.map((racer) =>
                characterPortraits && racer.id in characterPortraits
                    ? { ...racer, portrait_url: characterPortraits[racer.id] }
                    : racer,
            ),
        [race.racers.data, characterPortraits],
    );
    const placements = race.placements.data;
    const mentions = placements.filter((placement) => placement.medal === null);

    return (
        <>
            {racers
                .filter((racer) => !racer.portrait_url)
                .map((racer) => (
                    <CharacterPortraitListener
                        key={racer.id}
                        characterId={racer.id}
                        onAttached={reloadCharacterPortraits}
                    />
                ))}

            <div className="border-camel-900 mb-5 flex flex-col gap-3 border-b pb-4 sm:flex-row sm:items-end sm:justify-between">
                <p className="text-camel-400 text-base" aria-live="polite">
                    {racers.length} {racers.length === 1 ? "racer" : "racers"}
                    {leader && ` · ${leader.name} leads at ${leader.level}`}
                </p>
                <CheckedAgo at={checkedAt} />
            </div>

            <h2 className="sr-only">Medals</h2>
            <Podium placements={placements} targetLevel={race.target_level} />

            {race.state === "closing" ? (
                <div key="closing" className="mt-3 text-base">
                    <p className="text-camel-400">
                        Everyone else has until {formatDateTime(race.closes_at)} to reach {race.target_level} for an
                        honourable mention.
                    </p>
                    {mentions.length > 0 && (
                        <ul className="mt-2 flex flex-wrap gap-x-4 gap-y-1.5" aria-label="Honourable mentions so far">
                            {mentions.map((placement) => (
                                <li key={placement.position} className="text-camel-400 flex items-center gap-1.5">
                                    <ClassToken playableClass={placement.character.playable_class} />
                                    {placement.character.name}
                                    <span className="text-camel-500 text-sm tabular-nums">
                                        {formatDateTime(placement.reached_at)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            ) : (
                <p key="running" className="text-camel-400 mt-3 text-base">
                    Once bronze is claimed, everyone else has 48 hours to reach {race.target_level} for an honourable
                    mention.
                </p>
            )}

            <div className="mt-8">
                <ExperienceBar
                    size="grand"
                    racers={racers}
                    fillLevel={leader?.level ?? (placements.length > 0 ? race.target_level : 0)}
                    targetLevel={race.target_level}
                />
            </div>

            <section aria-labelledby="standings-heading" className="mt-8">
                <h2 id="standings-heading" className="text-camel-300 mb-2 font-serif text-xl text-balance">
                    Standings
                </h2>
                <Ladder racers={racers} leaderLevel={leader?.level ?? null} targetLevel={race.target_level} />
            </section>
        </>
    );
}

/**
 * The finished race: header, three-step podium and honourable mentions.
 */
function Results({ race }) {
    const placements = race.placements.data;
    const mentions = placements.filter((placement) => placement.medal === null);

    return (
        <>
            <p className="text-camel-400 text-base">Finished {formatDayMonth(race.closes_at)}</p>

            <div className="mx-auto mt-8 max-w-3xl">
                <Podium placements={placements} variant="steps" targetLevel={race.target_level} />
            </div>

            <section aria-labelledby="honourable-mentions-heading" className="mt-10">
                <h2 id="honourable-mentions-heading" className="text-camel-300 mb-2 font-serif text-xl text-balance">
                    Honourable Mentions
                </h2>
                {mentions.length > 0 ? (
                    <ul key="mentions" className="grid gap-x-7 sm:grid-cols-2">
                        {mentions.map((placement) => (
                            <li
                                key={placement.position}
                                className="border-camel-900 text-camel-400 flex items-center gap-2 border-b py-2 text-base"
                            >
                                <ClassToken playableClass={placement.character.playable_class} />
                                {placement.character.name}
                                <span className="text-camel-500 ml-auto text-sm tabular-nums">
                                    {formatDateTime(placement.reached_at)}
                                </span>
                            </li>
                        ))}
                    </ul>
                ) : (
                    <p key="no-mentions" className="text-camel-400 text-base">
                        Nobody else reached {race.target_level} in time.
                    </p>
                )}
            </section>
        </>
    );
}
