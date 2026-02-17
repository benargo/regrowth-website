import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Pagination from "@/Components/Pagination";

function ActionBadge({ action }) {
    const colors = {
        posted: "bg-green-700 text-green-200",
        updated: "bg-amber-700 text-amber-200",
        deleted: "bg-red-700 text-red-200",
    };

    return (
        <span className={`inline-block rounded px-2 py-0.5 text-xs font-medium ${colors[action] || "bg-gray-700 text-gray-300"}`}>
            {action}
        </span>
    );
}

export default function DailyQuestsAuditLog({ entries }) {
    return (
        <Master title="Daily Quests Audit Log">
            <SharedHeader
                title="Daily Quests Audit Log"
                backgroundClass="bg-dungeons"
            />
            <div className="container mx-auto px-4 py-8">
                {entries.data.length === 0 ? (
                    <p className="text-center text-gray-400">No audit log entries found.</p>
                ) : (
                    <>
                        <div className="overflow-x-auto rounded-lg border border-gray-700">
                            <table className="w-full text-left text-sm text-gray-300">
                                <thead className="bg-gray-800 text-xs uppercase text-gray-400">
                                    <tr>
                                        <th className="px-4 py-3">Timestamp</th>
                                        <th className="px-4 py-3">User</th>
                                        <th className="px-4 py-3">Action</th>
                                        <th className="px-4 py-3">Date</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-gray-700">
                                    {entries.data.map((entry, index) => (
                                        <tr key={index} className="hover:bg-gray-800/50">
                                            <td className="whitespace-nowrap px-4 py-3 font-mono text-xs">
                                                {entry.timestamp}
                                            </td>
                                            <td className="px-4 py-3">{entry.user}</td>
                                            <td className="px-4 py-3">
                                                <ActionBadge action={entry.action} />
                                            </td>
                                            <td className="px-4 py-3">{entry.date}</td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                        </div>
                        <Pagination
                            links={entries.links}
                            meta={entries.meta || { from: entries.from, to: entries.to, total: entries.total, last_page: entries.last_page }}
                            itemName="entries"
                        />
                    </>
                )}
            </div>
        </Master>
    );
}
