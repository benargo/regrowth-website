import { useEffect, useRef } from "react";
import Tooltip from "@/Components/Tooltip";
import ClassToken from "@/Components/Race/ClassToken";
import cn from "@/Helpers/cn";
import groupByLevel from "@/Helpers/groupByLevel";

const TOKEN_STEP_PX = 18;
const GRAND_MIN_STACK_PX = 272;
const NOTCH_STYLE = {
    backgroundImage:
        "repeating-linear-gradient(90deg, transparent 0 calc(5% - 1px), color-mix(in srgb, var(--color-camel-500) 35%, transparent) calc(5% - 1px) 5%)",
};

/**
 * The tick labels along the bar: 1, then every tenth level up to the target.
 */
function tickLabels(targetLevel) {
    const labels = [1];

    for (let level = 10; level <= targetLevel; level += 10) {
        labels.push(level);
    }

    return labels;
}

/**
 * A World of Warcraft style experience bar from level 1 to `targetLevel`,
 * split into 20 notches and filled to `fillLevel`, with each racer's class
 * icon stacked in a column above their level.
 *
 * `size` "strip" is the compact homepage bar; below the md breakpoint it shows
 * only the token of `focusRacerId`. `size` "grand" stands at least 290 px tall,
 * scrolls sideways on narrow screens starting at `fillLevel`, and gives each
 * token a name, class and level tooltip that opens on hover or tap. The grand
 * bar is hidden from assistive technology.
 */
export default function ExperienceBar({
    racers,
    fillLevel = 0,
    targetLevel = 60,
    size = "strip",
    focusRacerId = null,
}) {
    const isGrand = size === "grand";
    const scrollerRef = useRef(null);
    const columns = groupByLevel(racers);
    const tallestColumn = Math.max(1, ...columns.map((column) => column.racers.length));
    const positionOf = (level) => `${(Math.min(level, targetLevel) / targetLevel) * 100}%`;

    useEffect(() => {
        const scroller = scrollerRef.current;

        if (!isGrand || !scroller || scroller.scrollWidth <= scroller.clientWidth) {
            return;
        }

        scroller.scrollLeft = (fillLevel / targetLevel) * scroller.scrollWidth - scroller.clientWidth / 2;
    }, [isGrand, fillLevel, targetLevel]);

    const stackStyle = isGrand
        ? { height: Math.max(GRAND_MIN_STACK_PX, tallestColumn * TOKEN_STEP_PX + 8) }
        : { "--stack-rows": tallestColumn };

    return (
        <div
            ref={scrollerRef}
            aria-hidden={isGrand ? true : undefined}
            className={cn(isGrand && "-mx-4 overflow-x-auto px-4 pt-2 pb-1 md:mx-0 md:overflow-visible md:px-0")}
        >
            <div className={cn(isGrand && "min-w-[42rem] md:min-w-0")}>
                <div
                    className={cn("relative", !isGrand && "h-[18px] md:h-[calc(var(--stack-rows)*18px)]")}
                    style={stackStyle}
                >
                    {columns.map((column) => (
                        <div
                            key={column.level}
                            className="absolute bottom-1 flex -translate-x-1/2 flex-col-reverse items-center gap-0.5"
                            style={{ left: positionOf(column.level) }}
                        >
                            {column.racers.map((racer) =>
                                isGrand ? (
                                    <Tooltip
                                        key={racer.id}
                                        describe={false}
                                        openOnTap
                                        className="leading-[0]"
                                        body={`${racer.name} · ${racer.playable_class?.name ?? "Unknown class"} · Level ${racer.level}`}
                                    >
                                        <ClassToken
                                            playableClass={racer.playable_class}
                                            size="sm"
                                            alt=""
                                            className="hover:border-camel-300 transition-colors"
                                        />
                                    </Tooltip>
                                ) : (
                                    <ClassToken
                                        key={racer.id}
                                        playableClass={racer.playable_class}
                                        size="sm"
                                        alt=""
                                        className={cn(
                                            focusRacerId !== null &&
                                                racer.id !== focusRacerId &&
                                                "hidden md:inline-grid",
                                        )}
                                    />
                                ),
                            )}
                        </div>
                    ))}
                </div>

                <div
                    className={cn(
                        "border-camel-800 relative overflow-hidden rounded-[3px] border bg-[#00141b]",
                        isGrand ? "h-[18px]" : "h-4",
                    )}
                >
                    <div
                        className="from-forever-100 to-forever-500 absolute inset-y-0 left-0 bg-linear-to-b opacity-85"
                        style={{ width: positionOf(fillLevel) }}
                    />
                    <div className="absolute inset-0" style={NOTCH_STYLE} />
                </div>

                <div className="text-camel-500 mt-1 flex justify-between text-xs tabular-nums">
                    {tickLabels(targetLevel).map((label) => (
                        <span key={label}>{label}</span>
                    ))}
                </div>
            </div>
        </div>
    );
}
