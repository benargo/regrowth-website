import Icon from "@/Components/FontAwesome/Icon";
import formatDate from "@/Helpers/FormatDate";

export function EventsSkeleton() {
    const fakeRows = Array.from({ length: 5 });

    return (
        <div className="animate-pulse">
            <table className="w-full border-collapse">
                <thead className="border-b border-amber-600/30">
                    <tr>
                        {["w-32", "w-48", "w-36"].map((w, i) => (
                            <th key={i} className="px-4 py-3 text-left">
                                <div className={`h-4 ${w} rounded bg-brown-700`} />
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-brown-700">
                    {fakeRows.map((_, i) => (
                        <tr key={i}>
                            <td className="px-4 py-3">
                                <div className="h-4 w-28 rounded bg-brown-700" />
                            </td>
                            <td className="px-4 py-3">
                                <div className="h-4 w-44 rounded bg-brown-700" />
                            </td>
                            <td className="px-4 py-3">
                                <div className="h-4 w-32 rounded bg-brown-700" />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function EventsTable({ events }) {
    const rows = events?.data ?? events ?? [];

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
            <table className="w-full border-collapse">
                <thead className="border-b border-amber-600">
                    <tr>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Date</th>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Title</th>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Timings</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-brown-700">
                    {rows.map((event) => {
                        const startDate = new Date(event.start_time);
                        const endDate = new Date(event.end_time);
                        const dayOfWeek = startDate.toLocaleString("en-GB", { weekday: "long" });
                        const formattedDate = formatDate(event.start_time);
                        const formatTime = (date) =>
                            date.toLocaleString("en-GB", { hour: "2-digit", minute: "2-digit" });

                        return (
                            // TODO: Update href to the event page route once implemented
                            <tr key={event.id} className="transition-colors hover:bg-brown-800/50">
                                <td className="whitespace-nowrap px-4 py-3">
                                    <p className="text-xs text-gray-500">{dayOfWeek}</p>
                                    <p className="text-sm text-gray-300">
                                        <span className="md:hidden">{formattedDate.short}</span>
                                        <span className="hidden md:inline lg:hidden">{formattedDate.medium}</span>
                                        <span className="hidden lg:inline">{formattedDate.long}</span>
                                    </p>
                                </td>
                                <td className="px-4 py-3 text-sm font-medium text-white">{event.title}</td>
                                <td className="whitespace-nowrap px-4 py-3 text-sm text-gray-300">
                                    {formatTime(startDate)}–{formatTime(endDate)}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
