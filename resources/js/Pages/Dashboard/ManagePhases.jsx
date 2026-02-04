import { Deferred, router, useForm } from "@inertiajs/react";
import { useState } from "react";
import Master from "@/Layouts/Master";
import Icon from "@/Components/FontAwesome/Icon";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import Modal from "@/Components/Modal";
import SharedHeader from "@/Components/SharedHeader";
import TextInput from "@/Components/TextInput";

function GuildTagsLoadingSkeleton() {
    return (
        <div className="max-h-64 space-y-2 overflow-y-auto">
            {[...Array(5)].map((_, index) => (
                <div key={index} className="flex animate-pulse items-center gap-3 p-2">
                    <div className="h-4 w-4 rounded bg-amber-600/30"></div>
                    <div className="h-4 w-32 rounded bg-amber-600/30"></div>
                </div>
            ))}
        </div>
    );
}

function GuildTagsList({ allGuildTags, selectedTagIds, onToggleTag }) {
    if (!allGuildTags || allGuildTags.length === 0) {
        return <p className="text-gray-400">No Warcraft Logs tags available. Tags are synced from Warcraft Logs.</p>;
    }

    return (
        <div className="max-h-64 space-y-2 overflow-y-auto">
            {allGuildTags.data.map((tag) => (
                <label
                    key={tag.id}
                    className="flex cursor-pointer items-center gap-3 rounded p-2 hover:bg-amber-600/10"
                >
                    <input
                        type="checkbox"
                        checked={selectedTagIds.includes(tag.id)}
                        onChange={() => onToggleTag(tag.id)}
                        className="h-4 w-4 rounded border-amber-600 bg-brown-800/50 text-amber-600 focus:ring-amber-500"
                    />
                    <span className="text-white">{tag.name}</span>
                </label>
            ))}
        </div>
    );
}

export default function ManagePhases({ phases, current_phase, all_guild_tags }) {
    const [expanded, setExpanded] = useState({});
    const [editingPhase, setEditingPhase] = useState(null);
    const [editingTagsPhase, setEditingTagsPhase] = useState(null);

    const { data, setData, put, processing, errors, reset } = useForm({
        start_date: "",
    });

    const {
        data: tagsData,
        setData: setTagsData,
        put: putTags,
        processing: tagsProcessing,
        errors: tagsErrors,
        reset: resetTags,
    } = useForm({
        guild_tag_ids: [],
    });

    const togglePhase = (phaseId) => {
        setExpanded((prev) => ({
            ...prev,
            [phaseId]: !prev[phaseId],
        }));
    };

    const toParisDatetimeLocal = (isoString) => {
        if (!isoString) {
            return "";
        }
        const date = new Date(isoString);
        const parisDate = new Intl.DateTimeFormat("sv-SE", {
            timeZone: "Europe/Paris",
            year: "numeric",
            month: "2-digit",
            day: "2-digit",
            hour: "2-digit",
            minute: "2-digit",
            hour12: false,
        }).format(date);
        return parisDate.replace(" ", "T");
    };

    const openEditModal = (phase) => {
        setEditingPhase(phase);
        setData("start_date", toParisDatetimeLocal(phase.start_date));
    };

    const closeModal = () => {
        setEditingPhase(null);
        reset();
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        put(route("dashboard.phases.update", editingPhase.id), {
            preserveScroll: true,
            onSuccess: () => closeModal(),
        });
    };

    const openTagsModal = (phase) => {
        setEditingTagsPhase(phase);
        const currentTagIds = phase.guild_tags?.map((tag) => tag.id) || [];
        setTagsData("guild_tag_ids", currentTagIds);
    };

    const closeTagsModal = () => {
        setEditingTagsPhase(null);
        resetTags();
    };

    const handleTagsSubmit = (e) => {
        e.preventDefault();
        putTags(route("dashboard.phases.guild-tags.update", editingTagsPhase.id), {
            preserveScroll: true,
            onSuccess: () => closeTagsModal(),
        });
    };

    const toggleTag = (tagId) => {
        const currentIds = tagsData.guild_tag_ids;
        if (currentIds.includes(tagId)) {
            setTagsData(
                "guild_tag_ids",
                currentIds.filter((id) => id !== tagId),
            );
        } else {
            setTagsData("guild_tag_ids", [...currentIds, tagId]);
        }
    };

    const toggleCountAttendance = (tagId, currentValue) => {
        router.patch(
            route("wcl.guild-tags.toggle-attendance", { guildTag: tagId }),
            {
                count_attendance: !currentValue,
            },
            {
                preserveScroll: true,
            },
        );
    };

    const formatDate = (dateString, options = {}) => {
        const date = new Date(dateString);
        return date.toLocaleDateString("en-GB", {
            year: "numeric",
            month: "short",
            day: "numeric",
            hour: "2-digit",
            minute: "2-digit",
            timeZoneName: "short",
            ...options,
        });
    };

    const isLocalTimezoneDifferent = (dateString) => {
        const date = new Date(dateString);
        const localTime = date.toLocaleString("en-GB", { timeZone: undefined });
        const parisTime = date.toLocaleString("en-GB", { timeZone: "Europe/Paris" });
        return localTime !== parisTime;
    };

    return (
        <Master title="Manage TBC Phases">
            <SharedHeader title="Manage TBC Phases" backgroundClass="bg-officer-meeting" />
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    {phases.data.map((phase) => (
                        <div key={phase.id} className="my-4 rounded-md border border-amber-600">
                            <button
                                onClick={() => togglePhase(phase.id)}
                                className="flex w-full flex-row items-center gap-3 px-4 py-3 text-left transition-colors hover:bg-amber-600/10"
                            >
                                <span
                                    className={`flex flex-none items-center justify-items-center transition-transform duration-500 ${expanded[phase.id] ? "-rotate-180" : ""}`}
                                >
                                    <Icon icon="chevron-down" style="solid" />
                                </span>
                                <h3 className="flex-1 text-lg font-semibold">Phase {phase.id}</h3>
                                {phase.id === current_phase && (
                                    <div className="flex-none rounded-md bg-green-600 px-2 py-1 text-xs font-semibold text-white">
                                        Current Phase
                                    </div>
                                )}
                            </button>
                            {expanded[phase.id] && (
                                <div className="grid grid-cols-1 border-t border-amber-600 px-4 py-3 md:grid-cols-4">
                                    {/* Start date */}
                                    <div className="text-md my-4 md:mr-8">
                                        <h3 className="text-lg font-bold">
                                            {phase.start_date && new Date(phase.start_date) < new Date()
                                                ? "Phase started on"
                                                : "Phase starts on"}
                                        </h3>
                                        {phase.start_date && (
                                            <p>
                                                <span className="font-bold">Server time:</span>&nbsp;
                                                {formatDate(phase.start_date, { timeZone: "Europe/Paris" })}
                                            </p>
                                        )}
                                        {phase.start_date && isLocalTimezoneDifferent(phase.start_date) && (
                                            <p>
                                                <span className="font-bold">Local time:</span>&nbsp;
                                                {formatDate(phase.start_date)}
                                            </p>
                                        )}
                                        {!phase.start_date && <p>a date yet to be determined.</p>}
                                        <p className="flex justify-center md:justify-start">
                                            <button
                                                onClick={() => openEditModal(phase)}
                                                className="mt-2 flex items-center gap-4 rounded border border-amber-600 px-2 py-3 transition-colors hover:bg-amber-600/20"
                                            >
                                                <div className="mx-1 text-center">
                                                    <Icon icon="edit" style="regular" className="h-4 w-4" />
                                                </div>
                                                <div className="text-md mr-1">Edit phase start date</div>
                                            </button>
                                        </p>
                                    </div>
                                    {/* Raids */}
                                    <div className="text-md my-4 md:mr-8">
                                        <h3 className="text-lg font-bold">Raids in this phase</h3>
                                        {phase.raids.length > 0 ? (
                                            <ul>
                                                {phase.raids.map((raid) => (
                                                    <li key={raid.id} className="flex">
                                                        {raid.name}
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="flex">No raids assigned to this phase.</p>
                                        )}
                                    </div>
                                    {/* Bosses */}
                                    <div className="text-md my-4 md:mr-8">
                                        <h3 className="text-lg font-bold">Bosses in this phase</h3>
                                        {phase.bosses.length > 0 ? (
                                            <ul>
                                                {phase.bosses.map((boss) => (
                                                    <li key={boss.id} className="auto">
                                                        {boss.name}
                                                    </li>
                                                ))}
                                            </ul>
                                        ) : (
                                            <p className="auto mb-2">No bosses assigned to this phase.</p>
                                        )}
                                    </div>
                                    {/* Warcraft Logs Tags */}
                                    <div className="text-md my-4 md:mr-8">
                                        <h3 className="text-lg font-bold">Warcraft Logs Tags</h3>
                                        {phase.guild_tags?.length > 0 ? (
                                            <div className="items-top my-2 flex flex-col flex-wrap gap-2">
                                                <div className="mb-1 flex flex-row items-end gap-2">
                                                    <h2 className="flex-auto font-semibold">Tag name</h2>
                                                    <p className="w-16 flex-initial text-xs font-semibold">
                                                        Counts toward attendance
                                                    </p>
                                                </div>
                                                {phase.guild_tags.map((tag) => (
                                                    <div key={tag.id} className="flex flex-row items-center gap-2">
                                                        <span className="flex-auto">{tag.name}</span>
                                                        <span
                                                            className="w-16 flex-initial text-xs text-green-400"
                                                            title="Counts toward attendance"
                                                        >
                                                            <input
                                                                type="checkbox"
                                                                checked={tag.count_attendance}
                                                                onChange={() =>
                                                                    toggleCountAttendance(tag.id, tag.count_attendance)
                                                                }
                                                                className="h-4 w-4 rounded border-amber-600 bg-brown-800/50 text-amber-600 focus:ring-amber-500"
                                                            />
                                                        </span>
                                                    </div>
                                                ))}
                                            </div>
                                        ) : (
                                            <p className="auto mb-2">No tags assigned to this phase.</p>
                                        )}
                                        <p className="flex justify-center md:justify-start">
                                            <button
                                                onClick={() => openTagsModal(phase)}
                                                className="flex items-center gap-4 rounded border border-amber-600 px-2 py-3 transition-colors hover:bg-amber-600/20"
                                            >
                                                <div className="mx-1 text-center">
                                                    <Icon icon="tags" style="solid" className="h-4 w-4" />
                                                </div>
                                                <div className="text-md mr-1">Manage tags</div>
                                            </button>
                                        </p>
                                    </div>
                                </div>
                            )}
                        </div>
                    ))}
                </div>
            </div>

            {/* Edit Start Date Modal */}
            <Modal show={editingPhase !== null} onClose={closeModal} maxWidth="md">
                <form onSubmit={handleSubmit} className="p-6">
                    <h2 className="text-lg font-bold font-medium text-white">
                        Edit Phase {editingPhase?.id} Start Date
                    </h2>
                    <p className="mt-1 text-sm text-white">
                        Enter the start date and time in Europe/Paris timezone (server time).
                    </p>
                    <div className="mt-4">
                        <InputLabel htmlFor="start_date" value="Start Date (Europe/Paris)" className="text-gray-400" />
                        <TextInput
                            id="start_date"
                            type="datetime-local"
                            value={data.start_date}
                            onChange={(e) => setData("start_date", e.target.value)}
                            className="mt-1 block w-full bg-brown-800/50 text-white"
                        />
                        <InputError message={errors.start_date} className="mt-2" />
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={closeModal}
                            className="inline-flex items-center rounded-md border border-gray-300 bg-gray-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition duration-150 ease-in-out hover:bg-brown-600"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={processing}
                            className={`inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:border-primary hover:bg-amber-700 ${processing ? "opacity-25" : ""}`}
                        >
                            {processing ? "Saving..." : "Save"}
                        </button>
                    </div>
                </form>
            </Modal>

            {/* Edit Guild Tags Modal */}
            <Modal show={editingTagsPhase !== null} onClose={closeTagsModal} maxWidth="md">
                <form onSubmit={handleTagsSubmit} className="p-6">
                    <h2 className="text-lg font-bold font-medium text-white">
                        Manage Warcraft Logs Tags for Phase {editingTagsPhase?.id}
                    </h2>
                    <p className="mt-1 text-sm text-white">
                        Select which Warcraft Logs tags should be associated with this phase.
                    </p>
                    <div className="mt-4">
                        <Deferred data="all_guild_tags" fallback={<GuildTagsLoadingSkeleton />}>
                            <GuildTagsList
                                allGuildTags={all_guild_tags}
                                selectedTagIds={tagsData.guild_tag_ids}
                                onToggleTag={toggleTag}
                            />
                        </Deferred>
                        <InputError message={tagsErrors.guild_tag_ids} className="mt-2" />
                    </div>
                    <div className="mt-6 flex justify-end gap-3">
                        <button
                            type="button"
                            onClick={closeTagsModal}
                            className="inline-flex items-center rounded-md border border-gray-300 bg-gray-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white shadow-sm transition duration-150 ease-in-out hover:bg-brown-600"
                        >
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={tagsProcessing}
                            className={`inline-flex items-center rounded-md border border-transparent bg-amber-600 px-4 py-2 text-xs font-semibold uppercase tracking-widest text-white transition duration-150 ease-in-out hover:border-primary hover:bg-amber-700 ${tagsProcessing ? "opacity-25" : ""}`}
                        >
                            {tagsProcessing ? "Saving..." : "Save"}
                        </button>
                    </div>
                </form>
            </Modal>
        </Master>
    );
}
