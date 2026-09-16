import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import Tooltip from "@/Components/Tooltip";
import formatDate from "@/Helpers/FormatDate";
import formatDuration from "@/Helpers/FormatDuration";

export function Skeleton() {
    const fakeRows = Array.from({ length: 8 });

    return (
        <div className="animate-pulse">
            <table className="w-full border-collapse">
                <thead className="border-b border-ink-600/30">
                    <tr>
                        {["w-32", "w-48", "w-24", "w-24", "w-16", "w-8"].map((w, i) => (
                            <th key={i} className="px-4 py-3 text-left">
                                <div className={`h-4 ${w} rounded bg-ground-700`} />
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y divide-ink-600">
                    {fakeRows.map((_, i) => (
                        <tr key={i}>
                            <td className="px-4 py-3">
                                <div className="h-4 w-28 rounded bg-ground-700" />
                            </td>
                            <td className="px-4 py-3">
                                <div className="h-4 w-44 rounded bg-ground-700" />
                            </td>
                            <td className="px-4 py-3">
                                <div className="h-4 w-20 rounded bg-ground-700" />
                            </td>
                            <td className="px-4 py-3">
                                <div className="h-4 w-20 rounded bg-ground-700" />
                            </td>
                            <td className="px-4 py-3">
                                <div className="h-4 w-12 rounded bg-ground-700" />
                            </td>
                            <td className="px-4 py-3 text-center">
                                <div className="mx-auto h-4 w-4 rounded bg-ground-700" />
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>
        </div>
    );
}

export default function Table({ reports }) {
    const rows = reports?.data ?? reports ?? [];
    if (rows.length === 0) {
        return (
            <div className="py-16 text-center text-secondary-400">
                <Icon icon="scroll" style="solid" className="mb-4 text-4xl" />
                <p>No reports found.</p>
            </div>
        );
    }

    return (
        <div className="overflow-x-auto">
            <table className="w-full border-collapse">
                <thead className="border-b border-ink-600">
                    <tr>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-ink-500">Date</th>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-ink-500">Title</th>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-ink-500">Zone</th>
                        <th className="px-4 py-3 text-left text-sm font-semibold text-ink-500">Tag</th>
                        <th className="px-4 py-3 text-right text-sm font-semibold text-ink-500">Duration</th>
                        <th className="px-4 py-3 text-center text-sm font-semibold text-ink-500">Linked</th>
                    </tr>
                </thead>
                <tbody className="divide-y divide-ink-600">
                    {rows.map((report) => {
                        const startDate = new Date(report.start_time);
                        const dayOfWeek = startDate.toLocaleString("en-GB", { weekday: "long" });
                        const formattedDate = formatDate(report.start_time);

                        return (
                            <tr key={report.id} className="transition-colors hover:bg-ground-800/50">
                                <td className="whitespace-nowrap px-4 py-3">
                                    <p className="text-xs text-secondary-500">{dayOfWeek}</p>
                                    <p className="text-sm text-secondary-300">
                                        <span className="md:hidden">{formattedDate.short}</span>
                                        <span className="hidden md:inline lg:hidden">{formattedDate.medium}</span>
                                        <span className="hidden lg:inline">{formattedDate.long}</span>
                                    </p>
                                </td>
                                <td className="px-4 py-3 text-sm">
                                    <Link
                                        href={route("raiding.reports.show", report.id)}
                                        target="_blank"
                                        className="font-medium text-ink-400 hover:text-ink-300 hover:underline"
                                    >
                                        {report.title}
                                    </Link>
                                </td>
                                <td className="px-4 py-3 text-sm text-secondary-300">{report.zone?.name ?? "—"}</td>
                                <td className="px-4 py-3 text-sm text-secondary-300">{report.guild_tag?.name ?? "—"}</td>
                                <td className="px-4 py-3 text-right text-sm text-secondary-300">
                                    {formatDuration({ milliseconds: new Date(report.end_time) - startDate })}
                                </td>
                                <td className="px-4 py-3 text-center">
                                    {report.linked_reports_count > 0 ? (
                                        <Tooltip body={`${report.linked_reports_count}`} position="right">
                                            <Icon icon="link" style="solid" className="text-ink-500" />
                                        </Tooltip>
                                    ) : (
                                        <Icon icon="link" style="solid" className="text-secondary-600" />
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
