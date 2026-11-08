import ClassToken from "@/Components/Race/ClassToken";
import Medal, { MEDALS } from "@/Components/Race/Medal";
import { formatDateTime } from "@/Helpers/serverTime";
import cn from "@/Helpers/cn";

const MEDAL_ORDER = ["gold", "silver", "bronze"];
const STEP_VISUAL_ORDER = { silver: 1, gold: 2, bronze: 3 };
const STEP_HEIGHT_CLASSES = {
    gold: "h-24 md:h-32",
    silver: "h-18 md:h-24",
    bronze: "h-14 md:h-18",
};

/**
 * The placement holding the given medal, if it has been claimed.
 */
function placementFor(placements, medal) {
    return placements.find((placement) => placement.medal === medal) ?? null;
}

/**
 * The race podium for the given placements.
 *
 * `variant` "plinths" is the mid-race row of three places: an unclaimed place
 * has a dashed outline and reads "Gold is unclaimed"; a claimed place takes
 * the medal's colour and shows the character and when they reached
 * `targetLevel`. `variant` "steps" is the finished three-step podium, silver
 * on the left, gold raised in the centre and bronze on the right.
 */
export default function Podium({ placements, variant = "plinths", targetLevel = 60 }) {
    if (variant === "steps") {
        return (
            <ol className="grid grid-cols-[1fr_1.15fr_1fr] items-end gap-2 md:gap-3">
                {MEDAL_ORDER.map((medal) => {
                    const placement = placementFor(placements, medal);
                    const character = placement?.character;

                    return (
                        <li
                            key={medal}
                            className="flex flex-col items-center text-center"
                            style={{ "--medal": MEDALS[medal].color, order: STEP_VISUAL_ORDER[medal] }}
                        >
                            <div className="mb-3 flex flex-col items-center gap-1.5 px-1">
                                <ClassToken playableClass={character?.playable_class} size="lg" />
                                <span className="text-camel-300 font-serif text-base leading-tight text-balance break-words md:text-xl">
                                    {character?.name ?? `${MEDALS[medal].label} is unclaimed`}
                                </span>
                                <small className="text-camel-500 text-sm tabular-nums">
                                    {placement ? formatDateTime(placement.reached_at) : " "}
                                </small>
                            </div>
                            <div
                                className={cn(
                                    "border-t-(--medal) grid w-full place-items-center border-t-[3px] font-serif text-3xl text-(--medal) md:text-4xl",
                                    "bg-[linear-gradient(color-mix(in_srgb,var(--medal)_22%,var(--color-forever-800)),var(--color-forever-900))]",
                                    STEP_HEIGHT_CLASSES[medal],
                                )}
                            >
                                <span className="sr-only">{MEDALS[medal].label}, place </span>
                                {MEDALS[medal].position}
                            </div>
                        </li>
                    );
                })}
            </ol>
        );
    }

    return (
        <ol className="grid gap-2.5 sm:grid-cols-3">
            {MEDAL_ORDER.map((medal) => {
                const placement = placementFor(placements, medal);
                const character = placement?.character;

                return placement ? (
                    <li
                        key={`${medal}-claimed`}
                        className="border-(--medal) text-camel-300 flex items-center gap-2.5 rounded border bg-[color-mix(in_srgb,var(--medal)_12%,var(--color-forever-900))] px-3 py-2.5 text-sm"
                        style={{ "--medal": MEDALS[medal].color }}
                    >
                        <Medal medal={medal} />
                        <ClassToken playableClass={character?.playable_class} />
                        <div className="min-w-0">
                            <span className="block truncate font-serif text-lg">{character?.name}</span>
                            <small className="text-camel-400 block text-sm">
                                Reached {targetLevel} on {formatDateTime(placement.reached_at)}
                            </small>
                        </div>
                    </li>
                ) : (
                    <li
                        key={`${medal}-unclaimed`}
                        className="border-camel-800 text-camel-500 flex items-center gap-2.5 rounded border border-dashed px-3 py-2.5 text-base"
                    >
                        <Medal medal={medal} />
                        {MEDALS[medal].label} is unclaimed
                    </li>
                );
            })}
        </ol>
    );
}
