import { Link, useForm } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import FormField from "@/Components/FormField";
import PrimaryButton from "@/Components/PrimaryButton";
import InputError from "@/Components/InputError";
import Icon from "@/Components/FontAwesome/Icon";
import SpecRow from "@/Components/Characters/SpecRow";

function SectionHeading({ children }) {
    return (
        <h2 className="mb-4 flex items-center gap-3 text-xs font-bold uppercase tracking-[0.15em] text-amber-500/70">
            <span>{children}</span>
            <span className="h-px flex-1 bg-amber-600/20" />
        </h2>
    );
}

export default function Edit({ character, specializations }) {
    const { data, setData, patch, processing, errors } = useForm({
        specialization_ids: character.specializations?.map((s) => s.id) ?? [],
        raid_specialization_id: character.specializations?.find((s) => s.is_raid_spec)?.id ?? null,
        is_loot_councillor: character.is_loot_councillor ?? false,
    });

    function handleSubmit(e) {
        e.preventDefault();
        patch(route("management.characters.update", character.id));
    }

    function toggleSpec(specId) {
        const isCurrentlySelected = data.specialization_ids.includes(specId);
        const newIds = isCurrentlySelected
            ? data.specialization_ids.filter((id) => id !== specId)
            : [...data.specialization_ids, specId];

        const newRaidId =
            isCurrentlySelected && data.raid_specialization_id === specId
                ? null
                : data.raid_specialization_id;

        setData((prev) => ({
            ...prev,
            specialization_ids: newIds,
            raid_specialization_id: newRaidId,
        }));
    }

    function setRaidSpec(specId) {
        setData("raid_specialization_id", specId === data.raid_specialization_id ? null : specId);
    }

    const specErrors = data.specialization_ids
        ?.map((_, i) => errors[`specialization_ids.${i}`])
        .filter(Boolean);

    return (
        <Master title={`Edit · ${character.name}`}>
            <SharedHeader backgroundClass="bg-goldshire" title={`Edit: ${character.name}`} />

            <PageContainer>
                <form onSubmit={handleSubmit}>
                    {/* Character identity (read-only) */}
                    <div className="mb-8 flex items-center gap-4">
                        {character.playable_class?.icon_url && (
                            <img
                                src={character.playable_class.icon_url}
                                alt={character.playable_class.name}
                                className="h-12 w-12 rounded-lg border border-amber-600/30 shadow-lg shadow-black/40"
                            />
                        )}
                        <div>
                            <h2 className="text-xl font-bold text-white">{character.name}</h2>
                            <p className="text-sm text-gray-400">
                                {character.playable_class?.name ?? "Unknown class"}
                                {character.rank?.name ? ` · ${character.rank.name}` : ""}
                            </p>
                        </div>
                    </div>

                    <div className="space-y-8 lg:max-w-2xl">
                        {/* Specializations */}
                        <section>
                            <SectionHeading>Specializations</SectionHeading>
                            <p className="mb-4 text-sm text-gray-500">
                                Select which specs this character plays. Mark one as the{" "}
                                <span className="inline-flex items-center gap-1 text-amber-500">
                                    <Icon icon="star" style="solid" className="text-[11px]" /> raid spec
                                </span>
                                .
                            </p>

                            {specializations && specializations.length > 0 ? (
                                <div className="space-y-2">
                                    {specializations.map((spec) => (
                                        <SpecRow
                                            key={spec.id}
                                            spec={spec}
                                            isSelected={data.specialization_ids.includes(spec.id)}
                                            isRaidSpec={data.raid_specialization_id === spec.id}
                                            onToggle={() => toggleSpec(spec.id)}
                                            onSetRaid={() => setRaidSpec(spec.id)}
                                        />
                                    ))}
                                </div>
                            ) : (
                                <p className="text-sm text-gray-500">
                                    No specializations available for this class.
                                </p>
                            )}

                            {specErrors?.length > 0 && (
                                <InputError message={specErrors[0]} className="mt-2" />
                            )}
                            {errors.specialization_ids && (
                                <InputError message={errors.specialization_ids} className="mt-2" />
                            )}
                            {errors.raid_specialization_id && (
                                <InputError message={errors.raid_specialization_id} className="mt-2" />
                            )}
                        </section>

                        {/* Loot Council */}
                        <section>
                            <SectionHeading>Loot Council</SectionHeading>
                            <label className="flex cursor-pointer items-center gap-3 rounded border border-brown-700 bg-brown-800/30 px-4 py-3 transition-colors hover:border-brown-600">
                                <input
                                    type="checkbox"
                                    checked={data.is_loot_councillor}
                                    onChange={(e) => setData("is_loot_councillor", e.target.checked)}
                                    className="h-4 w-4 rounded border-amber-600 bg-brown-900 text-amber-600 focus:ring-amber-500 focus:ring-offset-0"
                                />
                                <span className="font-medium text-white">Loot Councillor</span>
                                <span className="text-sm text-gray-500">
                                    This character has a vote on loot distribution
                                </span>
                            </label>
                            {errors.is_loot_councillor && (
                                <InputError message={errors.is_loot_councillor} className="mt-2" />
                            )}
                        </section>

                        {/* Actions */}
                        <div className="flex items-center gap-4 border-t border-brown-700 pt-6">
                            <PrimaryButton type="submit" processing={processing}>
                                {processing ? "Saving…" : "Save Changes"}
                            </PrimaryButton>
                            <Link
                                href={route("management.characters.show", {
                                    character: character.id,
                                    slug: character.slug,
                                })}
                                className="text-sm text-gray-400 transition-colors hover:text-white"
                            >
                                Cancel
                            </Link>
                        </div>
                    </div>
                </form>
            </PageContainer>
        </Master>
    );
}
