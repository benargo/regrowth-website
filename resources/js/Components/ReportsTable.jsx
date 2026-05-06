import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import Tooltip from "@/Components/Tooltip";
import formatDate from "@/Helpers/FormatDate";
import formatDuration from "@/Helpers/FormatDuration";

export function ReportsSkeleton() {
    const fakeRows = Array.from({ length: 8 });

    return (
        <div className="animate-pulse">
            <table className="w-full border-collapse">
                <thead className="border-b border-amber-600/30">
                    <tr>
                        {["w-32", "w-48", "w-24", "w-24", "w-16", "w-8"].map((w, i) => (
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
                                <div className="h-4 w-20 rounded bg-brown-700" />
                            </td>
                            <td className="px-4 py-3">
                                <div className="h-4 w-20 rounded bg-brown-700" />
                            </td>
                            <td className="px-4 py-3">
                                <div className="h-4 w-12 rounded bg-brown-700" />
                            </td>
                            <td className="px-4 py-3 text-center">
                                <div className="mx-auto h-4 w-4 rounded bg-brown-700" />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function ReportsTable({ reports }) {
    const rows = reports?.data ?? reports ?? [];
    if (rows.length === 0) {
        return (
            <div className="py-16 text-center text-gray-400">
                <Icon icon="scroll" style="solid" className="mb-4 text-4xl" />
                <p>No reports found.</p>
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
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Zone</th>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-amber-500">Tag</th>
                        <th className="px-4 py-3 text-right text-sm font-semibold text-amber-500">Duration</th>
                        <th className="px-4 py-3 text-center text-sm font-semibold text-amber-500">Linked</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-brown-700">
                    {rows.map((report) => {
                        const startDate = new Date(report.start_time);
                        const dayOfWeek = startDate.toLocaleString("en-GB", { weekday: "long" });
                        const formattedDate = formatDate(report.start_time);

                        return (
                            <tr key={report.id} className="transition-colors hover:bg-brown-800/50">
                                <td className="whitespace-nowrap px-4 py-3">
                                    <p className="text-xs text-gray-500">{dayOfWeek}</p>
                                    <p className="text-sm text-gray-300">
                                        <span className="md:hidden">{formattedDate.short}</span>
                                        <span className="hidden md:inline lg:hidden">{formattedDate.medium}</span>
                                        <span className="hidden lg:inline">{formattedDate.long}</span>
                                    </p>
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Link
                                        href={route("raiding.reports.show", report.id)}
                                        target="_blank"
                                        className="font-medium text-amber-400 hover:text-amber-300 hover:underline"
                                    >
                                        {report.title}
                                    </Link>
                                </td>
                                <td className="px-4 py-3 text-sm text-gray-300">{report.zone?.name ?? "—"}</td>
                                <td className="px-4 py-3 text-sm text-gray-300">{report.guild_tag?.name ?? "—"}</td>
                                <td className="px-4 py-3 text-right text-sm text-gray-300">
                                    {formatDuration({ milliseconds: new Date(report.end_time) - startDate })}
                                </td>
                                <td className="px-4 py-3 text-center">
                                    {report.linked_reports_count > 0 ? (
                                        <Tooltip text={`${report.linked_reports_count}`} position="right">
                                            <Icon icon="link" style="solid" className="text-amber-500" />
                                        </Tooltip>
                                    ) : (
                                        <Icon icon="link" style="solid" className="text-gray-600" />
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>
        </div>
    );
}
