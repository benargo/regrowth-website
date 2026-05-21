import { Link, useForm } from "@inertiajs/react";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";
import FormContainer from "@/Components/FormContainer";
import FormField from "@/Components/FormField";
import TextInput from "@/Components/TextInput";
import PrimaryButton from "@/Components/PrimaryButton";

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

            <FormContainer>
                    <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                        {/* Title */}
                        <FormField label="Template name" htmlFor="title" error={errors.title}>
                            <TextInput
                                id="title"
                                type="text"
                                value={data.title}
                                onChange={(e) => setData("title", e.target.value)}
                                placeholder="e.g. SSC Default Setup"
                                className="w-full"
                            />
                        </FormField>

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
                            {errors.raid_ids && <p className="mt-1 text-sm text-red-400">{errors.raid_ids}</p>}
                        </div>

                        {/* Actions */}
                        <div className="flex items-center gap-4">
                            <PrimaryButton type="submit" processing={processing}>
                                {processing ? "Creating…" : "Create Template"}
                            </PrimaryButton>
                            <Link
                                href={route("dashboard.event-templates.index")}
                                className="text-sm text-gray-400 hover:text-gray-200"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
            </FormContainer>
        </Master>
    );
}
