import { useState } from "react";
import { router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import Alert from "@/Components/Alert";
import AutoSaveLabel from "@/Components/AutoSaveLabel";
import Autocomplete from "@/Components/Autocomplete";
import Checkbox from "@/Components/Checkbox";
import Icon from "@/Components/FontAwesome/Icon";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import TabNav from "@/Components/TabNav";
import parseAutocompleteSelection from "@/Helpers/ParseAutocompleteSelection";

export default function AddonSettings({ councillors: councillorsProp, ranks: ranksProp, tags: tagsProp, characters }) {
    const [ranks, setRanks] = useState(ranksProp?.data ?? []);
    const [tags, setTags] = useState(tagsProp?.data ?? []);
    const [characterSearch, setCharacterSearch] = useState("");
    const [isProcessing, setIsProcessing] = useState(false);

    // Rendered straight from props so the redirect-back refresh is the single
    // source of truth for the councillor list.
    const councillors = councillorsProp?.data ?? [];
    const councillorCounts = councillorsProp?.meta ?? { total: 0, mains: 0, alts: 0 };
    const councillorIds = new Set(councillors.map((c) => c.id));
    const availableCharacters = (characters ?? []).filter((c) => !councillorIds.has(c.id));

    const setLootCouncillor = (characterId, isLootCouncillor) => {
        if (isProcessing) return;
        setIsProcessing(true);
        router.patch(
            route("characters.update", characterId),
            { is_loot_councillor: isLootCouncillor },
            {
                preserveScroll: true,
                onSuccess: () => setCharacterSearch(""),
                onFinish: () => setIsProcessing(false),
            },
        );
    };

    const handleAutocompleteChange = (value) => {
        const characterId = parseAutocompleteSelection(value);
        if (characterId) {
            setLootCouncillor(characterId, true);
        } else {
            setCharacterSearch(value);
        }
    };

    const handleToggleRankAttendance = (rankId, currentValue) => {
        router.patch(
            route("management.ranks.toggle-attendance", rankId),
            {
                count_attendance: !currentValue,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setRanks(page.props.ranks.data);
                },
            },
        );
    };

    const handleToggleTagAttendance = (tagId, currentValue) => {
        router.patch(
            route("wcl.guild-tags.toggle-attendance", tagId),
            {
                count_attendance: !currentValue,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setTags(page.props.tags.data);
                },
            },
        );
    };

    return (
        <Master title="Addon Settings">
            <SharedHeader title="Addon Settings" backgroundClass="bg-officer-meeting" />
            <PageContainer>
                <TabNav
                    tabs={[
                        { name: "base64", label: "Base64", href: route("management.addon.export") },
                        { name: "json", label: "JSON", href: route("management.addon.export.json") },
                        { name: "schema", label: "Schema", href: route("management.addon.export.schema") },
                        { name: "settings", label: "Settings", href: route("management.addon.settings") },
                    ]}
                    currentTab="settings"
                />
                <div className="flex items-center justify-between gap-4">
                    <p className="flex-1">
                        This page allows you to configure various settings for the addon. Changes you make will be saved
                        automatically.
                    </p>
                    <AutoSaveLabel processing={isProcessing} />
                </div>
                <div className="my-6 md:mx-20">
                    <Alert type="info">
                        Do not make changes without agreement from the other officers. Any changed settings will affect
                        attendance calculations and loot council operations.
                    </Alert>
                </div>

                <div className="mt-8 grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="mb-4 rounded-lg border border-amber-600 p-4">
                        <h2 className="mb-2 flex flex-row items-center">
                            <Icon icon="user-friends" style="solid" className="mr-2 h-6 w-6" />
                            <span className="text-2xl font-semibold">Loot council members</span>
                        </h2>
                        <p className="text-mb mb-1 text-gray-200">
                            Configure which guild members are part of the loot council.
                        </p>
                        <p className="mb-1 text-sm text-gray-400">
                            {councillorCounts.total} total ({councillorCounts.mains} mains, {councillorCounts.alts}{" "}
                            alts)
                        </p>
                        {councillors.length > 0 ? (
                            <div className="mt-4">
                                {councillors.map((councillor) => (
                                    <div key={councillor.id} className="mb-2 flex flex-row items-center gap-4">
                                        <div className="flex h-12 flex-1 items-center rounded-md border border-brown-800 bg-brown-800/30 p-2">
                                            {councillor.name}
                                        </div>
                                        <div className="flex-none">
                                            <button
                                                className="flex h-12 w-12 items-center justify-center rounded bg-red-600 font-bold text-white hover:bg-red-800"
                                                onClick={() => setLootCouncillor(councillor.id, false)}
                                                disabled={isProcessing}
                                            >
                                                <Icon icon="trash-alt" style="solid" />
                                                <span className="sr-only">Remove</span>
                                            </button>
                                        </div>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="my-2 rounded-md border border-brown-800 p-2 text-sm text-gray-400">
                                No loot councillors configured.
                            </p>
                        )}
                        <div className="mt-4">
                            <Autocomplete
                                value={characterSearch}
                                onChange={handleAutocompleteChange}
                                options={availableCharacters}
                                placeholder="Add councillor by name…"
                                getOptionValue={(character) => String(character.id)}
                                getSearchableText={(character) => character.name}
                                renderOption={(character) => character.name}
                            />
                        </div>
                    </div>
                    <div className="mb-4 rounded-lg border border-amber-600 p-4">
                        <h2 className="mb-2 flex flex-row items-center">
                            <Icon icon="analytics" style="solid" className="mr-2 h-6 w-6" />
                            <span className="text-2xl font-semibold">Ranks to track attendance</span>
                        </h2>
                        <p className="text-mb text-grey-200 mb-1">
                            Select which guild ranks should be tracked for attendance.
                        </p>
                        <p className="mb-1 text-sm text-blue-400">
                            The fewer ranks you select, the more responsive the addon will be.
                        </p>
                        {ranks.length > 0 ? (
                            <div className="mt-4 rounded-md border border-brown-800">
                                {ranks.map((rank) => (
                                    <div
                                        key={rank.id}
                                        className="flex flex-row items-center border-b border-b-brown-800 first:rounded-t-md last:rounded-b-md"
                                    >
                                        <div className="mr-2 flex h-12 w-12 items-center justify-center border border-brown-800 bg-brown-800/50 p-2">
                                            <Checkbox
                                                checked={rank.count_attendance}
                                                onChange={() =>
                                                    handleToggleRankAttendance(rank.id, rank.count_attendance)
                                                }
                                                id={`rank-${rank.id}`}
                                            />
                                        </div>
                                        <label htmlFor={`rank-${rank.id}`}>{rank.name}</label>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="mt-2 text-sm text-gray-400">No ranks available.</p>
                        )}
                    </div>
                    <div className="mb-4 rounded-lg border border-amber-600 p-4">
                        <h2 className="mb-2 flex flex-row items-center">
                            <img src="/images/logo_warcraftlogs.webp" alt="Warcraft Logs" className="mr-2 h-6 w-6" />
                            <span className="text-2xl font-semibold">Warcraft Logs tags</span>
                        </h2>
                        <p className="text-mb mb-1 text-gray-200">
                            Select which Warcraft Logs tags should be used for attendance calculations.
                        </p>
                        {tags.length > 0 ? (
                            <div className="mt-4 rounded-md border border-brown-800">
                                {tags.map((tag) => (
                                    <div
                                        key={tag.id}
                                        className="flex flex-row items-center border-b border-b-brown-800 first:rounded-t-md last:rounded-b-md"
                                    >
                                        <div className="mr-2 flex h-12 w-12 items-center justify-center border border-brown-800 bg-brown-800/50 p-2">
                                            <Checkbox
                                                checked={tag.count_attendance}
                                                onChange={() => handleToggleTagAttendance(tag.id, tag.count_attendance)}
                                                id={`tag-${tag.id}`}
                                            />
                                        </div>
                                        <label htmlFor={`tag-${tag.id}`}>{tag.name}</label>
                                    </div>
                                ))}
                            </div>
                        ) : (
                            <p className="mt-2 text-sm text-gray-400">No tags available.</p>
                        )}
                    </div>
                </div>
            </PageContainer>
        </Master>
    );
}
