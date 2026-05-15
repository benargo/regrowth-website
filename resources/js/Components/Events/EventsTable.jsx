import Icon from "@/Components/FontAwesome/Icon";
import formatDate from "@/Helpers/FormatDate";
import usePermission from "@/Hooks/Permissions";
import { Link } from "@inertiajs/react";

export function EventsSkeleton() {
    const fakeRows = Array.from({ length: 3 });

    return (
        <div className="animate-pulse">
            <div className="flex border-b border-amber-600">
                <div className="w-40 shrink-0 px-4 py-3">
                    <div className="h-4 w-8 rounded bg-brown-700" />
                </div>
                <div className="min-w-0 flex-1 px-4 py-3">
                    <div className="h-4 w-10 rounded bg-brown-700" />
                </div>
                <div className="w-36 shrink-0 px-4 py-3">
                    <div className="h-4 w-16 rounded bg-brown-700" />
                </div>
            </div>
            <div className="divide-y divide-brown-700">
                {fakeRows.map((_, i) => (
                    <div key={i} className="flex items-center">
                        <div className="w-40 shrink-0 px-4 py-3">
                            <div className="mb-1 h-3 w-12 rounded bg-brown-700" />
                            <div className="h-4 w-24 rounded bg-brown-700" />
                        </div>
                        <div className="min-w-0 flex-1 px-4 py-3">
                            <div className="h-4 w-40 rounded bg-brown-700" />
                        </div>
                        <div className="w-36 shrink-0 px-4 py-3">
                            <div className="h-4 w-24 rounded bg-brown-700" />
                        </div>
                    </div>
                ))}
            </div>
        </div>
    );
}

export default function EventsTable({ events }) {
    const rows = events?.data ?? events ?? [];
    const canViewPlans = usePermission("view-raid-plans");

    if (rows.length === 0) {
        return (
            <div className="py-16 text-center text-gray-400">
                <Icon icon="calendar-xmark" style="solid" className="mb-4 text-4xl" />
                <p>No events scheduled this week.</p>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto">
            <div className="w-full">
                <div className="flex border-b border-amber-600">
                    <div className="w-40 shrink-0 px-4 py-3 text-left text-sm font-semibold text-amber-500">Date</div>
                    <div className="min-w-0 flex-1 px-4 py-3 text-left text-sm font-semibold text-amber-500">Title</div>
                    <div className="w-36 shrink-0 px-4 py-3 text-left text-sm font-semibold text-amber-500">
                        Timings
                    </div>
                </div>
                <div className="divide-y divide-brown-700">
                    {rows.map((event) => {
                        const startDate = new Date(event.start_time);
                        const endDate = new Date(event.end_time);
                        const dayOfWeek = startDate.toLocaleString("en-GB", { weekday: "long" });
                        const formattedDate = formatDate(event.start_time);
                        const formatTime = (date) =>
                            date.toLocaleString("en-GB", { hour: "2-digit", minute: "2-digit" });

                        const rowClassName =
                            "flex items-center transition-colors hover:bg-brown-800/50" +
                            (canViewPlans ? " cursor-pointer" : "");

                        const rowContent = (
                            <>
                                <div className="w-40 shrink-0 whitespace-nowrap px-4 py-3">
                                    <p className="text-xs text-gray-500">{dayOfWeek}</p>
                                    <p className="text-sm text-gray-300">
                                        <span className="md:hidden">{formattedDate.short}</span>
                                        <span className="hidden md:inline lg:hidden">{formattedDate.medium}</span>
                                        <span className="hidden lg:inline">{formattedDate.long}</span>
                                    </p>
                                </div>
                                <div className="min-w-0 flex-1 px-4 py-3 text-sm font-medium text-white">
                                    {event.title}
                                </div>
                                <div className="w-36 shrink-0 whitespace-nowrap px-4 py-3 text-sm text-gray-300">
                                    {formatTime(startDate)}–{formatTime(endDate)}
                                </div>
                            </>
                        );

                        return canViewPlans ? (
                            <Link key={event.id} className={rowClassName} href={route("raiding.plans.show", event.id)}>
                                {rowContent}
                            </Link>
                        ) : (
                            <div key={event.id} className={rowClassName}>
                                {rowContent}
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
