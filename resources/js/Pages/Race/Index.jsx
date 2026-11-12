import { useMemo, useState } from "react";
import { router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import Band from "@/Components/Band/Band";
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

const STANDINGS_FILL = "bg-black/25";

/**
 * The Race to Level 60 page. Opens with an introduction beside the WoW
 * Forever logo. While the race runs it shows the podium, then the grand
 * experience bar and the ladder of every racer in a wave-edged band; once
 * finished it shows the podium, then the honourable mentions. Updates the checked time from broadcasts and reloads `race`
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
            <div className="text-camel-400 flex-1 bg-[linear-gradient(var(--color-forever-800),var(--color-forever-900)_22rem)]">
                {!race ? (
                    <NoRace />
                ) : race.state === "finished" ? (
                    <Results race={race} />
                ) : (
                    <Standings
                       
                        race={race}
                        characterPortraits={characterPortraits}
                        checkedAt={latestOf(broadcastCheckedAt, race.checked_at)}
                    />
                )}
            </div>
        </Master>
    );
}

/**
 * The WoW Forever logo beside a paragraph explaining the race.
 */
function Introduction({ targetLevel }) {
    return (
        <div className="mb-10 flex flex-col items-center gap-5 text-center sm:flex-row sm:items-center sm:gap-7 sm:text-left">
            <img
                src="/images/logo_forever.webp"
                alt="World of Warcraft: Forever"
                width="830"
                height="670"
                className="h-auto w-36 shrink-0 sm:w-44"
            />
            <p className="text-camel-300 max-w-[62ch] font-serif text-lg leading-relaxed text-pretty md:text-xl">
                Regrowth is starting afresh on World of Warcraft: Forever, and every one of our characters is racing to
                level {targetLevel}. The first three to ding {targetLevel} take gold, silver and bronze, and this page
                follows the pack as it climbs.
            </p>
        </div>
    );
}

/**
 * The page when there is no Forever race.
 */
function NoRace() {
    return (
        <Band tone="light" maxWidth="max-w-5xl">
            <Introduction targetLevel={60} />
            <p className="text-camel-400 text-base">There is no race to follow yet.</p>
        </Band>
    );
}

/**
 * The race while it is running or closing: introduction, header and podium,
 * then the grand bar and ladder in a wave-edged band.
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
            <Band tone="light" maxWidth="max-w-5xl">
                <Introduction targetLevel={race.target_level} />

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
                        {leader && ` · ${leader.full_name} leads at ${leader.level}`}
                    </p>
                    <CheckedAgo at={checkedAt} />
                </div>

                <h2 className="sr-only">Medals</h2>
                <Podium placements={placements} targetLevel={race.target_level} />

                {race.state === "closing" ? (
                    <div className="mt-3 text-base">
                        <p className="text-camel-400">
                            Everyone else has until {formatDateTime(race.closes_at)} to reach {race.target_level} for an
                            honourable mention.
                        </p>
                        {mentions.length > 0 && (
                            <ul
                                className="mt-2 flex flex-wrap gap-x-4 gap-y-1.5"
                                aria-label="Honourable mentions so far"
                            >
                                {mentions.map((placement) => (
                                    <Mention key={placement.id} placement={placement} className="gap-1.5" />
                                ))}
                            </ul>
                        )}
                    </div>
                ) : (
                    <p className="text-camel-400 mt-3 text-base">
                        Once bronze is claimed, everyone else has 48 hours to reach {race.target_level} for an
                        honourable mention.
                    </p>
                )}
            </Band>

            <Band
                tone="shaded"
                edge="top"
                fillClassName={STANDINGS_FILL}
                maxWidth="max-w-5xl"
                aria-labelledby="standings-heading"
            >
                <ExperienceBar
                    size="grand"
                    racers={racers}
                    fillLevel={leader?.level ?? (placements.length > 0 ? race.target_level : 0)}
                    targetLevel={race.target_level}
                />

                <h2 id="standings-heading" className="text-camel-300 mt-10 mb-2 font-serif text-xl text-balance">
                    Standings
                </h2>
                <Ladder racers={racers} leaderLevel={leader?.level ?? null} targetLevel={race.target_level} />
            </Band>
        </>
    );
}

/**
 * One honourable mention: class token, character name and the time they reached the target level.
 */
function Mention({ placement, className = "", timeClassName = "" }) {
    return (
        <li className={`text-camel-400 flex items-center ${className}`}>
            <ClassToken playableClass={placement.character.playable_class} />
            {placement.character.full_name}
            <span className={`text-camel-500 text-sm tabular-nums ${timeClassName}`}>
                {formatDateTime(placement.reached_at)}
            </span>
        </li>
    );
}

/**
 * The finished race: introduction, header and three-step podium, then the
 * honourable mentions in a wave-edged band.
 */
function Results({ race }) {
    const placements = race.placements.data;
    const mentions = placements.filter((placement) => placement.medal === null);

    return (
        <>
            <Band tone="light" maxWidth="max-w-5xl">
                <Introduction targetLevel={race.target_level} />

                <p className="text-camel-400 text-base">Finished {formatDayMonth(race.closes_at)}</p>

                <div className="mx-auto mt-8 max-w-3xl">
                    <Podium placements={placements} variant="steps" targetLevel={race.target_level} />
                </div>
            </Band>

            <Band
                tone="shaded"
                edge="top"
                fillClassName={STANDINGS_FILL}
                maxWidth="max-w-5xl"
                aria-labelledby="honourable-mentions-heading"
            >
                <h2 id="honourable-mentions-heading" className="text-camel-300 mb-2 font-serif text-xl text-balance">
                    Honourable Mentions
                </h2>
                {mentions.length > 0 ? (
                    <ul className="grid gap-x-7 sm:grid-cols-2">
                        {mentions.map((placement) => (
                            <Mention
                                key={placement.id}
                                placement={placement}
                                className="border-camel-900 gap-2 border-b py-2 text-base"
                                timeClassName="ml-auto"
                            />
                        ))}
                    </ul>
                ) : (
                    <p className="text-camel-400 text-base">
                        Nobody else reached {race.target_level} in time.
                    </p>
                )}
            </Band>
        </>
    );
}
