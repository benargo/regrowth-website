import { Link, useForm } from "@inertiajs/react";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";

export default function Create({ raids }) {
    const { data, setData, post, processing, errors } = useForm({
        title: "",
        raid_ids: [],
    });

    function toggleRaid(raidId) {
        setData("raid_ids", data.raid_ids.includes(raidId)
            ? data.raid_ids.filter((id) => id !== raidId)
            : [...data.raid_ids, raidId]
        );
    }

    function handleSubmit(e) {
        e.preventDefault();
        post(route("dashboard.event-templates.store"));
    }

    return (
        <Master title="Create Event Template">
            <SharedHeader backgroundClass="bg-ssctk" title="Create Event Template" />

            <div className="py-12 text-white">
                <div className="container mx-auto max-w-xl px-4">
                    <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                        {/* Title */}
                        <div className="flex flex-col gap-1">
                            <label htmlFor="title" className="text-sm font-semibold text-gray-300">
                                Template name
                            </label>
                            <input
                                id="title"
                                type="text"
                                value={data.title}
                                onChange={(e) => setData("title", e.target.value)}
                                placeholder="e.g. SSC Default Setup"
                                className="rounded border border-gray-600 bg-brown-800/60 px-3 py-2 text-white placeholder-gray-500 focus:border-amber-500 focus:outline-none"
                            />
                            {errors.title && <p className="text-sm text-red-400">{errors.title}</p>}
                        </div>

                        {/* Raids */}
                        <div className="flex flex-col gap-2">
                            <p className="text-sm font-semibold text-gray-300">Raids</p>
                            {raids.length === 0 ? (
                                <p className="text-sm text-gray-500">No raids available.</p>
                            ) : (
                                <div className="flex flex-col gap-2">
                                    {raids.map((raid) => (
                                        <label
                                            key={raid.id}
                                            className="flex cursor-pointer items-center gap-3 rounded border border-gray-700 px-3 py-2 transition-colors hover:border-amber-600/50 hover:bg-amber-600/10"
                                        >
                                            <input
                                                type="checkbox"
                                                checked={data.raid_ids.includes(raid.id)}
                                                onChange={() => toggleRaid(raid.id)}
                                                className="accent-amber-500"
                                            />
                                            <span>{raid.name}</span>
                                        </label>
                                    ))}
                                </div>
                            )}
                            {errors.raid_ids && <p className="text-sm text-red-400">{errors.raid_ids}</p>}
                        </div>

                        {/* Actions */}
                        <div className="flex items-center gap-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className="rounded bg-amber-600 px-5 py-2 text-sm font-semibold text-white transition-colors hover:bg-amber-500 disabled:opacity-50"
                            >
                                {processing ? "Creating…" : "Create Template"}
                            </button>
                            <Link
                                href={route("dashboard.event-templates.index")}
                                className="text-sm text-gray-400 hover:text-gray-200"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
                </div>
            </div>
        </Master>
    );
}
