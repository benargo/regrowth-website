import { useState } from "react";
import { router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import Alert from "@/Components/Alert";
import Checkbox from "@/Components/Checkbox";
import Icon from "@/Components/FontAwesome/Icon";
import SharedHeader from "@/Components/SharedHeader";
import TabNav from "@/Components/TabNav";

export default function AddonSettings({ settings, characters }) {
    const [councillors, setCouncillors] = useState(settings.councillors || []);
    const [ranks, setRanks] = useState(settings.ranks || []);
    const [tags, setTags] = useState(settings.tags || []);
    const [newCouncillorName, setNewCouncillorName] = useState("");
    const [isProcessing, setIsProcessing] = useState(false);
    const [showSaved, setShowSaved] = useState(false);

    const handleAddCouncillor = () => {
        if (!newCouncillorName.trim() || isProcessing) return;
        setIsProcessing(true);
        router.post(
            route("dashboard.addon.settings.councillors.add"),
            {
                character_name: newCouncillorName,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setCouncillors(page.props.settings.councillors);
                    setNewCouncillorName("");
                    setIsProcessing(false);
                    setShowSaved(true);
                    setTimeout(() => setShowSaved(false), 2000);
                },
                onError: () => {
                    setIsProcessing(false);
                },
            },
        );
    };

    const handleRemoveCouncillor = (characterId) => {
        if (isProcessing) return;
        setIsProcessing(true);
        router.delete(route("dashboard.addon.settings.councillors.remove", characterId), {
            preserveScroll: true,
            onSuccess: (page) => {
                setCouncillors(page.props.settings.councillors);
                setIsProcessing(false);
            },
            onError: () => {
                setIsProcessing(false);
            },
        });
    };

    const handleToggleRankAttendance = (rankId, currentValue) => {
        router.patch(
            route("dashboard.ranks.toggle-attendance", rankId),
            {
                count_attendance: !currentValue,
            },
            {
                preserveScroll: true,
                onSuccess: (page) => {
                    setRanks(page.props.settings.ranks);
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
                    setTags(page.props.settings.tags);
                },
            },
        );
    };

    return (
        <Master title="Addon Settings">
            <SharedHeader title="Addon Settings" backgroundClass="bg-officer-meeting" />
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    <TabNav
                        tabs={[
                            { name: "base64", label: "Base64", href: route("dashboard.addon.export") },
                            { name: "json", label: "JSON", href: route("dashboard.addon.export.json") },
                            { name: "schema", label: "Schema", href: route("dashboard.addon.export.schema") },
                            { name: "settings", label: "Settings", href: route("dashboard.addon.settings") },
                        ]}
                        currentTab="settings"
                    />
                    <div className="flex items-center justify-between gap-4">
                        <p className="flex-1">
                            This page allows you to configure various settings for the addon. Changes you make will be
                            saved automatically.
                        </p>
                        {isProcessing && (
                            <div className="flex-none text-sm font-medium text-amber-400">
                                <Icon icon="spinner" style="solid" className="fa-spin mr-2" />
                                Saving...
                            </div>
                        )}
                        {!isProcessing && showSaved && (
                            <div className="flex-none text-sm font-medium text-green-400">
                                <Icon icon="check" style="solid" className="mr-2" />
                                Saved
                            </div>
                        )}
                    </div>
                    <div className="my-6 md:mx-20">
                        <Alert type="info">
                            Do not make changes without agreement from the other officers. Any changed settings will
                            affect attendance calculations and loot council operations.
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
                                                    onClick={() => handleRemoveCouncillor(councillor.id)}
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
                            <div className="mt-4 flex flex-row items-center gap-4">
                                <input
                                    type="text"
                                    list="member-list"
                                    placeholder="Add councillor by name..."
                                    className="h-12 flex-1 rounded-md border border-brown-800 bg-brown-800/50 p-2 text-white focus:border-amber-600 focus:outline-none focus:ring-2 focus:ring-amber-600"
                                    value={newCouncillorName}
                                    onChange={(e) => setNewCouncillorName(e.target.value)}
                                    onKeyDown={(e) => {
                                        if (e.key === "Enter") {
                                            e.preventDefault();
                                            handleAddCouncillor();
                                        }
                                    }}
                                    disabled={isProcessing}
                                />
                                <datalist id="member-list">
                                    {characters?.length > 0 &&
                                        characters?.map((character) => (
                                            <option key={character.id} value={character.name} />
                                        ))}
                                </datalist>
                                <button
                                    className="h-12 w-12 flex-none rounded-md border border-green-800 bg-green-600 font-bold text-white hover:bg-green-800"
                                    onClick={handleAddCouncillor}
                                    disabled={isProcessing}
                                >
                                    <Icon icon="plus" style="solid" />
                                    <span className="sr-only">Add Councillor</span>
                                </button>
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
                                <img
                                    src="/images/logo_warcraftlogs.webp"
                                    alt="Warcraft Logs"
                                    className="mr-2 h-6 w-6"
                                />
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
                                                    onChange={() =>
                                                        handleToggleTagAttendance(tag.id, tag.count_attendance)
                                                    }
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
                </div>
            </div>
        </Master>
    );
}
