import { Link } from "@inertiajs/react";
import Section from "@/Components/Forever/Section";
import DisplayHeading from "@/Components/Forever/DisplayHeading";

/**
 * Format an ISO timestamp in the viewer's own timezone. The server sends
 * UTC; raiders are spread across several countries, so the browser decides.
 */
function formatDate(iso) {
    return new Date(iso).toLocaleDateString(undefined, {
        weekday: "short",
        day: "numeric",
        month: "short",
    });
}

function formatTime(iso) {
    return new Date(iso).toLocaleTimeString(undefined, {
        hour: "2-digit",
        minute: "2-digit",
    });
}

/**
 * A skeleton row, shown while the deferred events prop is in flight.
 */
function SkeletonRow() {
    return (
        <li className="border-ground-600/60 flex animate-pulse items-center gap-4 border-b py-4">
            <div className="bg-ground-600/60 h-4 w-28 rounded" />
            <div className="bg-ground-600/40 h-4 w-44 rounded" />
        </li>
    );
}

/**
 * The row body. Identical whether or not it is wrapped in a link, so
 * anonymous visitors and members see exactly the same information.
 */
function EventRow({ event }) {
    return (
        <>
            <span className="text-ink-400 w-full shrink-0 text-sm tabular-nums sm:w-44">
                <time dateTime={event.start_time}>
                    {formatDate(event.start_time)} · {formatTime(event.start_time)}
                </time>
            </span>
            <span className="text-ink-200 font-serif text-lg">{event.title}</span>
            {event.raids.length > 0 && (
                <span className="text-ink-500 hidden text-sm md:inline">{event.raids.join(", ")}</span>
            )}
        </>
    );
}

/**
 * Upcoming raids, visible to everyone.
 *
 * Rows link to the raid plan only for authenticated users: raiding.plans.show
 * is not a public route, so linking it for anonymous visitors would be a dead
 * end.
 */
export default function UpcomingEvents({ events, canViewPlans = false }) {
    const isLoading = events === undefined;

    return (
        <Section tone="mid" edge="bottom" edgeTone="deep" className="py-16 md:py-24">
            <div className="container mx-auto max-w-3xl px-4">
                <DisplayHeading level={2} eyebrow="What's next" className="mb-10 text-center">
                    Upcoming Raids
                </DisplayHeading>

                {isLoading ? (
                    <ul className="border-ground-600/60 border-t">
                        <SkeletonRow />
                        <SkeletonRow />
                        <SkeletonRow />
                    </ul>
                ) : events.length === 0 ? (
                    <p className="text-ink-400 text-center">
                        No raids are scheduled right now. Check back soon, or ask in Discord.
                    </p>
                ) : (
                    <ul className="border-ground-600/60 border-t">
                        {events.map((event) => (
                            <li key={event.id} className="border-ground-600/60 border-b">
                                {canViewPlans ? (
                                    <Link
                                        href={route("raiding.plans.show", event.id)}
                                        className="hover:bg-ground-700/60 flex flex-wrap items-baseline gap-x-4 gap-y-1 px-2 py-4 transition-colors"
                                    >
                                        <EventRow event={event} />
                                    </Link>
                                ) : (
                                    <div className="flex flex-wrap items-baseline gap-x-4 gap-y-1 px-2 py-4">
                                        <EventRow event={event} />
                                    </div>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </Section>
    );
}
