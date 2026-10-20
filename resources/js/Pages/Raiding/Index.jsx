import { Deferred, Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import EmptyState from "@/Components/EmptyState";
import EventsTable, { EventsSkeleton } from "@/Components/Events/EventsTable";
import Table, { Skeleton } from "@/Components/Raiding/Reports/Table";
import PageContainer from "@/Components/PageContainer";
import SectionNav from "@/Components/Navigation/SectionNav";
import SectionCards from "@/Components/Navigation/SectionCards";

function RecentReports({ reports }) {
    if (reports.length === 0) {
        return <EmptyState icon="scroll" message="No reports for this game version." />;
    }

    return <Table reports={{ data: reports }} />;
}

export default function Index({ upcomingEvents, reports }) {
    return (
        <Master title="Raiding with Regrowth">
            <SharedHeader title="Raiding with Regrowth" backgroundClass="bg-illidan" />
            <SectionNav section="raiding" />
            <PageContainer>
                <p className="mb-8 max-w-prose text-lg">
                    Join us for epic raids, thrilling boss battles, and unforgettable moments. Whether you're a seasoned
                    raider or new to the game, our community welcomes you to experience the excitement of raiding with
                    Regrowth.
                </p>

                <SectionCards section="raiding" />

                <h2 className="mb-4 text-2xl font-bold">On this week</h2>
                <Deferred data="upcomingEvents" fallback={<EventsSkeleton />}>
                    <EventsTable events={upcomingEvents ?? []} emptyMessage="No upcoming events for this game version." />
                </Deferred>

                <div className="mt-12 mb-4 flex items-center gap-3">
                    <h2 className="flex-1 text-2xl font-bold">Recent reports</h2>
                    <Link
                        href={route("raiding.reports.index")}
                        className="inline-flex items-center gap-2 rounded-md border border-amber-600 px-4 py-2 text-sm font-semibold text-amber-500 transition duration-150 ease-in-out hover:bg-amber-700 hover:text-white"
                    >
                        View all reports
                        <Icon icon="arrow-right" style="solid" className="h-4" aria-hidden="true" />
                    </Link>
                </div>
                <Deferred data="reports" fallback={<Skeleton />}>
                    <RecentReports reports={reports ?? []} />
                </Deferred>
            </PageContainer>
        </Master>
    );
}
