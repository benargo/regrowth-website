import { useState } from "react";
import { Deferred, useForm } from "@inertiajs/react";
import AddonPageLayout from "@/Components/LootTool/AddonPageLayout";
import AddonSettingsPanel, { AddonSettingsChecklist } from "@/Components/LootTool/AddonSettingsPanel";
import Alert from "@/Components/Alert";
import Autocomplete from "@/Components/Autocomplete";
import EmptyState from "@/Components/EmptyState";
import Icon from "@/Components/FontAwesome/Icon";
import { ErrorSummary, firstError, SaveButton, SecondaryButton } from "@/Components/FormControls";
import FormPageLayout, { FormPageSection, useErrorSummaryFocus } from "@/Components/FormPage";
import IconButton from "@/Components/IconButton";
import cn from "@/Helpers/cn";
import parseAutocompleteSelection from "@/Helpers/ParseAutocompleteSelection";
import { usePermission } from "@/Hooks/usePermission";

const SECTIONS = [
    { value: "loot-council", label: "Loot council" },
    { value: "attendance-ranks", label: "Attendance ranks" },
    { value: "attendance-tags", label: "Warcraft Logs tags" },
];

/**
 * Whether two lists of ids hold the same ids, in any order.
 */
function hasSameIds(firstIds, secondIds) {
    const secondIdSet = new Set(secondIds);

    return firstIds.length === secondIdSet.size && firstIds.every((id) => secondIdSet.has(id));
}

/**
 * The selected game version's addon settings: loot councillors, the ranks that count towards
 * attendance and the Warcraft Logs tags that count towards attendance, saved together.
 */
export default function AddonSettings({ gameVersion: gameVersionResource, councillors, ranks, tags, characters }) {
    const gameVersion = gameVersionResource?.data ?? null;

    return (
        <AddonPageLayout title="Addon Settings" active="settings" gameVersion={gameVersion}>
            {gameVersion === null ? (
                <div className="rounded-md border border-dashed border-amber-700/70">
                    <EmptyState icon="box-open" message="No game version is set up yet." />
                </div>
            ) : (
                <SettingsForm
                    key={gameVersion.id}
                    gameVersion={gameVersion}
                    councillors={councillors?.data ?? []}
                    ranks={ranks?.data ?? []}
                    tags={tags?.data ?? []}
                    characters={characters?.data}
                />
            )}
        </AddonPageLayout>
    );
}

/**
 * The settings form for one game version, seeded from its saved settings. Sections the viewer
 * may not change are disabled and left out of the saved payload.
 */
function SettingsForm({ gameVersion, councillors, ranks, tags, characters }) {
    const canEditCouncillors = usePermission("update-characters");
    const canEditRanks = usePermission("edit-datasets");
    const canEditTags = usePermission("update-warcraft-logs-tags");
    const { summaryRef, focusSummary } = useErrorSummaryFocus();
    const [characterSearch, setCharacterSearch] = useState("");

    const savedCouncillorIds = councillors.map((councillor) => councillor.id);
    const savedRankIds = ranks.filter((rank) => rank.count_attendance).map((rank) => rank.id);
    const savedTagIds = tags.filter((tag) => tag.count_attendance).map((tag) => tag.id);

    const form = useForm({
        game_version: gameVersion.slug,
        councillors,
        counted_rank_ids: savedRankIds,
        counted_tag_ids: savedTagIds,
    });

    const councillorIds = new Set(form.data.councillors.map((councillor) => councillor.id));
    const isDirty =
        !hasSameIds([...councillorIds], savedCouncillorIds) ||
        !hasSameIds(form.data.counted_rank_ids, savedRankIds) ||
        !hasSameIds(form.data.counted_tag_ids, savedTagIds);
    const availableCharacters = (characters ?? []).filter((character) => !councillorIds.has(character.id));

    function addCouncillor(value) {
        const characterId = parseAutocompleteSelection(value);
        const character = availableCharacters.find((candidate) => candidate.id === characterId);

        if (!character) {
            setCharacterSearch(value);

            return;
        }

        form.setData(
            "councillors",
            [...form.data.councillors, character].sort((first, second) => first.name.localeCompare(second.name)),
        );
        setCharacterSearch("");
    }

    function removeCouncillor(characterId) {
        form.setData(
            "councillors",
            form.data.councillors.filter((councillor) => councillor.id !== characterId),
        );
    }

    function submit(event) {
        event.preventDefault();

        form.transform(({ game_version, councillors: selectedCouncillors, counted_rank_ids, counted_tag_ids }) => ({
            game_version,
            ...(canEditCouncillors ? { councillor_ids: selectedCouncillors.map((councillor) => councillor.id) } : {}),
            ...(canEditRanks ? { counted_rank_ids } : {}),
            ...(canEditTags ? { counted_tag_ids } : {}),
        }));

        form.put(route("addons.loot-tool.settings.update"), {
            preserveScroll: true,
            onSuccess: () => form.setDefaults(),
            onError: () => focusSummary(),
        });
    }

    function reset() {
        form.reset();
        form.clearErrors();
        setCharacterSearch("");
    }

    /**
     * Stops Enter in a text field from submitting the form, so only the Save button saves.
     */
    function preventImplicitSubmit(event) {
        if (event.key === "Enter" && event.target instanceof HTMLInputElement) {
            event.preventDefault();
        }
    }

    /**
     * Stops Enter on a checkbox from reaching its handler, which would otherwise submit the form.
     */
    function preventCheckboxSubmit(event) {
        if (event.key === "Enter" && event.target.getAttribute("role") === "checkbox") {
            event.stopPropagation();
        }
    }

    return (
        <form onSubmit={submit} onKeyDown={preventImplicitSubmit} onKeyUpCapture={preventCheckboxSubmit} noValidate>
            <p className="mb-6 text-gray-200">
                Settings for <strong className="text-white">{gameVersion.title}</strong>. Use the game version switcher
                to change another version.
            </p>

            <div className="mb-8">
                <Alert type="info">
                    Do not make changes without agreement from the other officers. Any changed settings will affect
                    attendance calculations and loot council operations.
                </Alert>
            </div>

            <FormPageLayout
                sections={SECTIONS}
                sidebar={<SettingsActions isDirty={isDirty} processing={form.processing} onReset={reset} />}
            >
                <ErrorSummary errors={form.errors} summaryRef={summaryRef} />

                <FormPageSection id="loot-council" title="Loot council">
                    <p className="-mt-3 text-gray-300">
                        The mains on the {gameVersion.title} loot council. The addon shows their votes on each item.
                    </p>
                    <AddonSettingsPanel
                        legend={`Councillors (${form.data.councillors.length})`}
                        disabled={!canEditCouncillors}
                        disabledReason="You don't have permission to change loot councillors."
                        error={firstError(form.errors, "councillor_ids")}
                    >
                        {form.data.councillors.length > 0 ? (
                            <ul className="divide-y divide-amber-700/30">
                                {form.data.councillors.map((councillor) => (
                                    <CouncillorRow
                                        key={councillor.id}
                                        councillor={councillor}
                                        onRemove={() => removeCouncillor(councillor.id)}
                                    />
                                ))}
                            </ul>
                        ) : (
                            <p className="px-4 py-8 text-center text-sm text-gray-400 sm:px-5">
                                No loot councillors yet. Add one below.
                            </p>
                        )}
                        <div className="border-t border-amber-700/50 bg-black/15 px-4 py-4 sm:px-5">
                            <label
                                htmlFor="councillor-search"
                                className="mb-1.5 block text-sm font-medium text-gray-300"
                            >
                                Add a councillor
                            </label>
                            <Deferred data="characters" fallback={<CharacterSearchSkeleton />}>
                                <Autocomplete
                                    id="councillor-search"
                                    value={characterSearch}
                                    onChange={addCouncillor}
                                    options={availableCharacters}
                                    placeholder="Search mains by name…"
                                    getOptionValue={(character) => String(character.id)}
                                    getSearchableText={(character) => character.name}
                                    renderOption={(character) => character.name}
                                />
                            </Deferred>
                        </div>
                    </AddonSettingsPanel>
                </FormPageSection>

                <FormPageSection id="attendance-ranks" title="Attendance ranks">
                    <p className="-mt-3 text-gray-300">
                        The addon tracks attendance for characters in these ranks. The fewer ranks it tracks, the more
                        responsive it is. Add, rename and reorder ranks on the game version&rsquo;s page.
                    </p>
                    <AddonSettingsPanel
                        legend="Counts towards attendance"
                        disabled={!canEditRanks}
                        disabledReason="You don't have permission to change guild ranks."
                        error={firstError(form.errors, "counted_rank_ids")}
                    >
                        <AddonSettingsChecklist
                            name="counted_rank_ids"
                            options={ranks}
                            selectedIds={form.data.counted_rank_ids}
                            onChange={(ids) => form.setData("counted_rank_ids", ids)}
                            emptyMessage={`${gameVersion.title} has no guild ranks yet.`}
                            renderLabel={(rank) => <span className={`text-guild-rank-${rank.slug}`}>{rank.name}</span>}
                        />
                    </AddonSettingsPanel>
                </FormPageSection>

                <FormPageSection id="attendance-tags" title="Warcraft Logs tags">
                    <p className="-mt-3 text-gray-300">
                        Reports with these tags count towards attendance. Tags belong to the Warcraft Logs guild, so a
                        change applies to every game version that uses it.
                    </p>
                    <AddonSettingsPanel
                        legend="Counts towards attendance"
                        disabled={!canEditTags}
                        disabledReason="You don't have permission to change Warcraft Logs tags."
                        error={firstError(form.errors, "counted_tag_ids")}
                    >
                        <AddonSettingsChecklist
                            name="counted_tag_ids"
                            options={tags}
                            selectedIds={form.data.counted_tag_ids}
                            onChange={(ids) => form.setData("counted_tag_ids", ids)}
                            emptyMessage={`${gameVersion.title} has no Warcraft Logs tags. Link a Warcraft Logs guild on the game version's page to add them.`}
                        />
                    </AddonSettingsPanel>
                </FormPageSection>
            </FormPageLayout>
        </form>
    );
}

/**
 * Save and Reset for the settings form, with an "Unsaved changes" hint while `isDirty`.
 */
function SettingsActions({ isDirty, processing, onReset }) {
    return (
        <div className="flex flex-wrap items-center gap-2 lg:mt-4 lg:flex-col lg:items-stretch">
            <p aria-live="polite" className="flex items-center gap-2 text-sm text-amber-200 lg:order-first">
                <Icon icon="circle" style="solid" className={cn("text-[0.5rem]", !isDirty && "hidden")} />
                <span className={cn(!isDirty && "hidden")}>Unsaved changes</span>
                <span className={cn("text-gray-400", isDirty && "hidden")}>All changes saved</span>
            </p>
            <div className="flex items-center gap-2">
                <SecondaryButton onClick={onReset} disabled={!isDirty || processing}>
                    Reset
                </SecondaryButton>
                <SaveButton processing={processing} disabled={!isDirty} label="Save changes" />
            </div>
        </div>
    );
}

/**
 * A loot councillor's portrait, name and rank, with a button to remove them.
 */
function CouncillorRow({ councillor, onRemove }) {
    return (
        <li className="flex items-center gap-3 px-4 py-2 sm:px-5">
            <img
                src={councillor.portrait_url ?? undefined}
                alt=""
                className={cn("size-9 rounded border border-amber-600/40", !councillor.portrait_url && "hidden")}
            />
            <div
                aria-hidden="true"
                className={cn(
                    "size-9 rounded border border-amber-600/40 bg-gray-700",
                    councillor.portrait_url && "hidden",
                )}
            />
            <div className="min-w-0 flex-1">
                <p className="truncate text-white">{councillor.name}</p>
                <p className={cn("text-xs", councillor.rank ? `text-guild-rank-${councillor.rank.slug}` : "hidden")}>
                    {councillor.rank?.name}
                </p>
            </div>
            <IconButton
                icon="times"
                label={`Remove ${councillor.name}`}
                onClick={onRemove}
                className="flex size-9 flex-none items-center justify-center rounded-md text-gray-300 hover:bg-red-700 hover:text-white disabled:cursor-not-allowed disabled:opacity-50 disabled:hover:bg-transparent"
            />
        </li>
    );
}

/**
 * The pulsing placeholder shown in place of the councillor search while the mains load.
 */
function CharacterSearchSkeleton() {
    return (
        <div
            role="status"
            aria-live="polite"
            className="bg-brown-800 h-10.5 animate-pulse rounded border border-amber-600/50"
        >
            <span className="sr-only">Loading characters…</span>
        </div>
    );
}
