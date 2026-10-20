import { CloseButton } from "@headlessui/react";
import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import cn from "@/Helpers/cn";

/**
 * The start time as weekday and time in the viewer's locale, e.g. "Wednesday at 19:30".
 */
function formatStartTime(startTime) {
    const date = new Date(startTime);
    const weekday = new Intl.DateTimeFormat(undefined, { weekday: "long" }).format(date);
    const time = new Intl.DateTimeFormat(undefined, { hour: "2-digit", minute: "2-digit" }).format(date);

    return `${weekday} at ${time}`;
}

function NextRaidSkeleton({ compact }) {
    return (
        <div className="animate-pulse space-y-3" aria-hidden="true">
            <div className={cn("rounded bg-white/10", compact ? "h-5 w-2/3" : "h-6 w-3/4")} />
            <div className="h-4 w-1/2 rounded bg-white/10" />
            <div className="h-4 w-2/3 rounded bg-white/10" />
            <div className="h-9 w-32 rounded bg-white/10" />
        </div>
    );
}

/**
 * The next raid of the current game version: its title, start time, the
 * viewer's signup line and a link to its plan. A skeleton while the navigation
 * data loads, and an empty state when there is no upcoming raid. `classes`
 * overrides the card, heading, signup icon, signup label and button colours.
 */
export default function NextRaidCard({ navigation, compact = false, classes = {} }) {
    const nextRaid = navigation?.next_raid;
    const signup = nextRaid?.signup;

    return (
        <section
            aria-label="Next raid"
            className={cn(
                "rounded-lg border border-amber-600 bg-amber-950/30",
                compact ? "p-3" : "p-5",
                classes.nextRaidCard,
            )}
        >
            {!compact && (
                <h2 className={cn("mb-3 text-sm font-semibold text-amber-300", classes.nextRaidHeading)}>Next raid</h2>
            )}

            {navigation === undefined ? (
                <NextRaidSkeleton compact={compact} />
            ) : nextRaid ? (
                <div className={cn("flex flex-col", compact ? "gap-2" : "gap-3")}>
                    <div>
                        <p className={cn("font-serif text-white", compact ? "text-lg" : "text-2xl")}>
                            {nextRaid.title}
                        </p>
                        <p className={cn("text-gray-300", compact ? "text-xs" : "text-sm")}>
                            <time dateTime={nextRaid.start_time}>{formatStartTime(nextRaid.start_time)}</time>
                        </p>
                    </div>

                    <div className={cn("flex items-center gap-3", !signup && "hidden")}>
                        <span
                            key={signup?.icon ?? "none"}
                            className={cn(
                                "flex h-8 w-8 flex-none items-center justify-center rounded bg-amber-600/20 text-amber-300",
                                classes.nextRaidIcon,
                                (compact || !signup?.icon) && "hidden",
                            )}
                            aria-hidden="true"
                        >
                            <Icon icon={signup?.icon ?? "circle"} style="solid" className="h-4 w-4" />
                        </span>
                        <p className={cn("text-amber-100", compact ? "text-xs" : "text-sm", classes.nextRaidSignup)}>
                            {signup?.label}
                        </p>
                    </div>

                    <div>
                        <CloseButton
                            as={Link}
                            href={nextRaid.plan_url}
                            className={cn(
                                "inline-flex items-center rounded-md bg-amber-700 font-semibold text-white transition-colors hover:bg-amber-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300",
                                compact ? "px-3 py-1.5 text-xs" : "px-4 py-2 text-sm",
                                classes.nextRaidButton,
                            )}
                        >
                            Open the plan
                        </CloseButton>
                    </div>
                </div>
            ) : (
                <p className={cn("text-gray-300", compact ? "text-xs" : "text-sm")}>
                    No upcoming raids for this game version.
                </p>
            )}
        </section>
    );
}
