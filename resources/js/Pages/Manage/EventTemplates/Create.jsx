import { Link, useForm } from "@inertiajs/react";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";
import FormContainer from "@/Components/FormContainer";
import { Checkbox, FieldError, FormRow, PrimaryButton, TextInput } from "@/Components/FormControls";

export default function Create({ raids }) {
    const { data, setData, post, processing, errors } = useForm({
        title: "",
        raid_ids: [],
    });

    function toggleRaid(raidId) {
        setData(
            "raid_ids",
            data.raid_ids.includes(raidId) ? data.raid_ids.filter((id) => id !== raidId) : [...data.raid_ids, raidId],
        );
    }

    function handleSubmit(e) {
        e.preventDefault();
        post(route("management.event-templates.store"));
    }

    return (
        <Master title="Create Event Template">
            <SharedHeader backgroundClass="bg-vashj-and-kaelthas" title="Create Event Template" />

            <FormContainer>
                <form onSubmit={handleSubmit} className="flex flex-col gap-6">
                    {/* Title */}
                    <FormRow label="Template name" error={errors.title}>
                        <TextInput
                            value={data.title}
                            onChange={(e) => setData("title", e.target.value)}
                            placeholder="e.g. SSC Default Setup"
                            invalid={!!errors.title}
                        />
                    </FormRow>

                    {/* Raids */}
                    <div className="flex flex-col gap-2">
                        <p className="text-sm font-semibold text-secondary-300">Raids</p>
                        {raids.length === 0 ? (
                            <p className="text-sm text-secondary-500">No raids available.</p>
                        ) : (
                            <div className="flex flex-col gap-2">
                                {raids.map((raid) => (
                                    <label
                                        key={raid.id}
                                        className="flex cursor-pointer items-center gap-3 rounded border border-secondary-700 px-3 py-2 transition-colors hover:border-ink-600/50 hover:bg-ink-600/10"
                                    >
                                        <Checkbox
                                            checked={data.raid_ids.includes(raid.id)}
                                            onChange={() => toggleRaid(raid.id)}
                                        />
                                        <span>{raid.name}</span>
                                    </label>
                                ))}
                            </div>
                        )}
                        <FieldError message={errors.raid_ids} className="mt-1" />
                    </div>

                    {/* Actions */}
                    <div className="flex items-center gap-4">
                        <PrimaryButton type="submit" processing={processing}>
                            {processing ? "Creating…" : "Create template"}
                        </PrimaryButton>
                        <Link
                            href={route("management.event-templates.index")}
                            className="text-sm text-secondary-400 hover:text-secondary-200"
                        >
                            Cancel
                        </Link>
                    </div>
                </form>
            </FormContainer>
        </Master>
    );
}
