import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import Tooltip from "@/Components/Tooltip";
import formatDate from "@/Helpers/FormatDate";
import formatDuration from "@/Helpers/FormatDuration";
import { Table as BaseTable, TableHead, TableBody, TableRow, TableHeader, TableCell } from "@/Components/Table";

export function Skeleton() {
    const fakeRows = Array.from({ length: 8 });

    return (
        <div className="animate-pulse">
            <BaseTable>
                <TableHead className="border-amber-600/30">
                    <tr>
                        {["w-32", "w-48", "w-24", "w-24", "w-16", "w-8"].map((w, i) => (
                            <TableHeader key={i} className="text-left">
                                <div className={`h-4 ${w} bg-brown-700 rounded`} />
                            </TableHeader>
                        ))}
                    </tr>
                </TableHead>
                <TableBody>
                    {fakeRows.map((_, i) => (
                        <tr key={i}>
                            <TableCell>
                                <div className="bg-brown-700 h-4 w-28 rounded" />
                            </TableCell>
                            <TableCell>
                                <div className="bg-brown-700 h-4 w-44 rounded" />
                            </TableCell>
                            <TableCell>
                                <div className="bg-brown-700 h-4 w-20 rounded" />
                            </TableCell>
                            <TableCell>
                                <div className="bg-brown-700 h-4 w-20 rounded" />
                            </TableCell>
                            <TableCell>
                                <div className="bg-brown-700 h-4 w-12 rounded" />
                            </TableCell>
                            <TableCell className="text-center">
                                <div className="bg-brown-700 mx-auto h-4 w-4 rounded" />
                            </TableCell>
                        </tr>
                    ))}
                </TableBody>
            </BaseTable>
        </div>
    );
}

export default function Table({ reports }) {
    const rows = reports?.data ?? reports ?? [];
    if (rows.length === 0) {
        return (
            <div className="text-gray-400 py-16 text-center">
                <Icon icon="scroll" style="solid" className="mb-4 text-4xl" />
                <p>No reports found.</p>
            </div>
        );
    }

    return (
        <BaseTable>
            <TableHead>
                <tr>
                    <TableHeader className="text-left">Date</TableHeader>
                    <TableHeader className="text-left">Title</TableHeader>
                    <TableHeader className="text-left">Zone</TableHeader>
                    <TableHeader className="text-left">Tag</TableHeader>
                    <TableHeader className="text-right">Duration</TableHeader>
                    <TableHeader className="text-center">Linked</TableHeader>
                </tr>
            </TableHead>
            <TableBody>
                {rows.map((report) => {
                    const startDate = new Date(report.start_time);
                    const dayOfWeek = startDate.toLocaleString("en-GB", { weekday: "long" });
                    const formattedDate = formatDate(report.start_time);

                    return (
                        <TableRow key={report.id}>
                            <TableCell className="whitespace-nowrap">
                                <p className="text-gray-500 text-xs">{dayOfWeek}</p>
                                <p className="text-gray-300 text-sm">
                                    <span className="md:hidden">{formattedDate.short}</span>
                                    <span className="hidden md:inline lg:hidden">{formattedDate.medium}</span>
                                    <span className="hidden lg:inline">{formattedDate.long}</span>
                                </p>
                            </TableCell>
                            <TableCell className="text-sm">
                                <Link
                                    href={route("raiding.reports.show", report.id)}
                                    target="_blank"
                                    className="text-amber-400 hover:text-amber-300 font-medium hover:underline"
                                >
                                    {report.title}
                                </Link>
                            </TableCell>
                            <TableCell className="text-gray-300 text-sm">{report.zone?.name ?? "—"}</TableCell>
                            <TableCell className="text-gray-300 text-sm">
                                {report.guild_tag?.name ?? "—"}
                            </TableCell>
                            <TableCell className="text-gray-300 text-right text-sm">
                                {formatDuration({ milliseconds: new Date(report.end_time) - startDate })}
                            </TableCell>
                            <TableCell className="text-center">
                                {report.linked_reports_count > 0 ? (
                                    <Tooltip body={`${report.linked_reports_count}`} position="right">
                                        <Icon icon="link" style="solid" className="text-amber-500" />
                                    </Tooltip>
                                ) : (
                                    <Icon icon="link" style="solid" className="text-gray-600" />
                                )}
                            </TableCell>
                        </TableRow>
                    );
                })}
            </TableBody>
        </BaseTable>
    );
}
