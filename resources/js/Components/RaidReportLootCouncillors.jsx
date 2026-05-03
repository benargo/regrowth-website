import { useState, useRef } from "react";
import { router, usePage } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import GuildRankLabel from "@/Components/GuildRankLabel";
import Autocomplete from "@/Components/Autocomplete";
import Tooltip from "@/Components/Tooltip";
import usePermission from "@/Hooks/Permissions";

export default function RaidReportLootCouncillors({ reportId, characters, onChange }) {
    const isCreateMode = reportId === null;
    const hasManagePermission = usePermission("manage-reports");
    const canManage = isCreateMode || hasManagePermission;
    const { lootCouncillorCandidates } = usePage().props;

    const [localCouncillors, setLocalCouncillors] = useState([]);
    const [isAddingCouncillor, setIsAddingCouncillor] = useState(false);
    const [isLoadingCandidates, setIsLoadingCandidates] = useState(false);
    const [processingIds, setProcessingIds] = useState(new Set());
    const [characterSearch, setCharacterSearch] = useState("");
    const candidatesLoadDone = useRef(false);

    const currentCouncillors = isCreateMode
        ? [...localCouncillors].sort((a, b) => a.name.localeCompare(b.name))
        : (characters ?? []).filter((c) => c.pivot?.is_loot_councillor).sort((a, b) => a.name.localeCompare(b.name));
    const councillorIds = new Set(currentCouncillors.map((c) => c.id));

    if (currentCouncillors.length === 0 && !canManage) {
        return null;
    }

    const handleOpenAdd = () => {
        setIsAddingCouncillor(true);
        if (!candidatesLoadDone.current) {
            candidatesLoadDone.current = true;
            setIsLoadingCandidates(true);
            router.reload({
                only: ["lootCouncillorCandidates"],
                preserveState: true,
                onFinish: () => setIsLoadingCandidates(false),
            });
        }
    };

    const handleCancelAdd = () => {
        setIsAddingCouncillor(false);
        setCharacterSearch("");
    };

    const addToProcessing = (id) => setProcessingIds((prev) => new Set([...prev, id]));
    const removeFromProcessing = (id) =>
        setProcessingIds((prev) => {
            const next = new Set(prev);
            next.delete(id);
            return next;
        });

    const handleAdd = (characterId) => {
        if (isCreateMode) {
            const character = (lootCouncillorCandidates ?? []).find((c) => c.id === characterId);
            if (!character) {
                return;
            }
            const updated = [...localCouncillors, character];
            setLocalCouncillors(updated);
            setCharacterSearch("");
            onChange?.(updated.map((c) => c.id));
            return;
        }

        addToProcessing(characterId);
        router.patch(
            route("raiding.reports.update", { report: reportId }),
            { loot_councillors: { action: "create", character_ids: [characterId] } },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    setCharacterSearch("");
                    router.reload({
                        only: ["lootCouncillorCandidates"],
                        preserveState: true,
                    });
                },
                onFinish: () => removeFromProcessing(characterId),
            },
        );
    };

    const handleRemove = (characterId) => {
        if (isCreateMode) {
            const updated = localCouncillors.filter((c) => c.id !== characterId);
            setLocalCouncillors(updated);
            onChange?.(updated.map((c) => c.id));
            return;
        }

        addToProcessing(characterId);
        router.patch(
            route("raiding.reports.update", { report: reportId }),
            { loot_councillors: { action: "delete", character_ids: [characterId] } },
            {
                preserveScroll: true,
                preserveState: true,
                onSuccess: () => {
                    if (isAddingCouncillor) {
                        router.reload({
                            only: ["lootCouncillorCandidates"],
                            preserveState: true,
                        });
                    }
                },
                onFinish: () => removeFromProcessing(characterId),
            },
        );
    };

    const handleAutocompleteChange = (val) => {
        const numVal = Number(val);
        if (Number.isInteger(numVal) && numVal > 0) {
            handleAdd(numVal);
        } else {
            setCharacterSearch(val);
        }
    };

    const availableCandidates = (lootCouncillorCandidates ?? []).filter((c) => !councillorIds.has(c.id));

    return (
        <div className="mt-6">
            <h2 className="mb-4 text-xl font-semibold text-white">
                Loot Council
                {currentCouncillors.length > 0 && (
                    <span className="ml-2 text-base font-normal text-gray-400">({currentCouncillors.length})</span>
                )}
            </h2>

            {currentCouncillors.length === 0 ? (
                <p className="text-gray-400">No loot councillors recorded.</p>
            ) : (
                <div className="divide-y divide-brown-700 rounded border border-amber-600/30">
                    {currentCouncillors.map((character) => (
                        <div key={character.id} className="flex items-center justify-between px-4 py-3">
                            <div className="flex items-center gap-3">
                                {character.playable_class?.icon_url && (
                                    <img
                                        src={character.playable_class.icon_url}
                                        alt={character.playable_class.name}
                                        className="h-6 w-6 rounded-sm"
                                    />
                                )}
                                <div className="flex flex-row items-center gap-2">
                                    <span className="text-sm font-medium text-white">{character.name}</span>
                                    {character.rank && (
                                        <div className="mt-0.5">
                                            <GuildRankLabel rank={character.rank} className="text-xs" />
                                        </div>
                                    )}
                                </div>
                            </div>
                            {canManage && (
                                <Tooltip text={"Remove " + character.name + " as councillor"} position="left">
                                    <button
                                        type="button"
                                        disabled={processingIds.has(character.id)}
                                        onClick={() => handleRemove(character.id)}
                                        className="rounded px-3 py-1.5 text-gray-500 transition-colors hover:bg-red-700/20 hover:text-red-400 disabled:cursor-not-allowed disabled:opacity-40"
                                    >
                                        <Icon icon="times" style="solid" className="text-xs" />
                                    </button>
                                </Tooltip>
                            )}
                        </div>
                    ))}
                </div>
            )}

            {canManage && !isAddingCouncillor && (
                <button
                    type="button"
                    onClick={handleOpenAdd}
                    className="mt-4 inline-flex items-center gap-2 rounded border border-amber-600/50 px-4 py-2 text-sm text-gray-300 transition-colors hover:border-amber-600 hover:bg-amber-600/10 hover:text-white"
                >
                    <Icon icon="plus" style="solid" className="text-amber-500" />
                    Add Councillor
                </button>
            )}

            {canManage && isAddingCouncillor && (
                <div className="mt-4">
                    {isLoadingCandidates ? (
                        <div className="h-10 w-full animate-pulse rounded border border-amber-600/30 bg-brown-800" />
                    ) : (
                        <Autocomplete
                            value={characterSearch}
                            onChange={handleAutocompleteChange}
                            options={availableCandidates}
                            placeholder="Search councillors…"
                            getOptionValue={(c) => String(c.id)}
                            getSearchableText={(c) => c.name}
                            renderOption={(c) => (
                                <span className="flex items-center gap-2">
                                    {c.playable_class?.icon_url && (
                                        <img
                                            src={c.playable_class.icon_url}
                                            alt={c.playable_class.name}
                                            className="h-4 w-4 rounded-sm"
                                        />
                                    )}
                                    {c.name}
                                </span>
                            )}
                        />
                    )}
                    <button
                        type="button"
                        onClick={handleCancelAdd}
                        className="mt-2 text-sm text-gray-400 hover:text-white"
                    >
                        Cancel
                    </button>
                </div>
            )}
        </div>
    );
}
