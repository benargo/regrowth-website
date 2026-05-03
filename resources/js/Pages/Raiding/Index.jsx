import { Link } from "@inertiajs/react";
import { Deferred } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import EventsTable, { EventsSkeleton } from "@/Components/EventsTable";
import ReportsTable, { ReportsSkeleton } from "@/Components/ReportsTable";

export default function Index({ upcomingEvents, reports }) {
    return (
        <Master title="Raiding with Regrowth">
            <SharedHeader title="Raiding with Regrowth" backgroundClass="bg-illidan" />
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    <p className="mb-8 text-lg">
                        Join us for epic raids, thrilling boss battles, and unforgettable moments. Whether you're a
                        seasoned raider or new to the game, our community welcomes you to experience the excitement of
                        raiding with Regrowth.
                    </p>

                    <h2 className="mb-4 text-2xl font-bold">On this week</h2>
                    <Deferred data="upcomingEvents" fallback={<EventsSkeleton />}>
                        <EventsTable events={upcomingEvents ?? []} />
                    </Deferred>

                    <h2 className="mb-4 mt-12 text-2xl font-bold">Recent reports</h2>
                    <div className="mb-4 flex justify-end">
                        <Link
                            href={route("raiding.reports.index")}
                            className="inline-flex items-center gap-2 rounded-md border border-amber-600 px-4 py-2 text-sm font-semibold text-amber-500 transition duration-150 ease-in-out hover:bg-amber-600 hover:text-white"
                        >
                            View all reports
                            <Icon icon="arrow-right" style="solid" className="h-4" />
                        </Link>
                    </div>
                    <Deferred data="reports" fallback={<ReportsSkeleton />}>
                        <ReportsTable reports={reports ?? { data: [] }} />
                    </Deferred>
                </div>
            </div>
        </Master>
    );
}
