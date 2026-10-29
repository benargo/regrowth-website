import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import EmptyState from "@/Components/EmptyState";
import Pagination from "@/Components/Pagination";
import EventsTable from "@/Components/Events/EventsTable";
import SectionNav from "@/Components/Navigation/SectionNav";
import { SITE_SECTIONS } from "@/Helpers/siteSections";
import { useResetQueryOnGameVersionSwitch } from "@/Hooks/useGameVersionSwitch";

/**
 * The current game version's upcoming events and its paginated past events.
 * A version switch drops the page's query string, so it lands on page 1.
 */
export default function Index({ hasAnyEvents, upcomingEvents, pastEvents }) {
    useResetQueryOnGameVersionSwitch();

    return (
        <Master title="Events">
            <SharedHeader title="Events" subtitle="Raiding" backgroundClass={SITE_SECTIONS.raiding.headerBackgroundClass} />
            <SectionNav section="raiding" />
            <PageContainer>
                {!hasAnyEvents ? (
                    <EmptyState icon="calendar-times" message="Events are not available for this game version." />
                ) : (
                    <div className="flex flex-col gap-12">
                        <section aria-labelledby="upcoming-events-heading">
                            <h2 id="upcoming-events-heading" className="mb-4 font-serif text-2xl text-amber-200">
                                Upcoming
                            </h2>
                            <EventsTable events={upcomingEvents} emptyMessage="No upcoming events for this game version." />
                        </section>
                        <section aria-labelledby="past-events-heading">
                            <h2 id="past-events-heading" className="mb-4 font-serif text-2xl text-amber-200">
                                Past
                            </h2>
                            <EventsTable events={pastEvents} emptyMessage="No past events to show." />
                            <Pagination links={pastEvents.meta.links} meta={pastEvents.meta} itemName="past events" />
                        </section>
                    </div>
                )}
            </PageContainer>
        </Master>
    );
}
