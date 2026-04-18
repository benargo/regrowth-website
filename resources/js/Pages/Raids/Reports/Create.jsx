import { useMemo, useState } from "react";
import { Link, router, usePage } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import InputError from "@/Components/InputError";
import DateFilterButton from "@/Components/DateFilterButton";
import Autocomplete from "@/Components/Autocomplete";
import LinkedRaidReports from "@/Components/LinkedRaidReports";
import RaidReportLootCouncillors from "@/Components/RaidReportLootCouncillors";

function getUtcOffsetMinutes(tz) {
    const now = new Date();
    const utcDate = new Date(now.toLocaleString("en-US", { timeZone: "UTC" }));
    const tzDate = new Date(now.toLocaleString("en-US", { timeZone: tz }));
    return (tzDate - utcDate) / 60000;
}

function formatOffsetDiff(diffMin) {
    const abs = Math.abs(diffMin);
    const h = Math.floor(abs / 60);
    const m = abs % 60;
    if (h === 0) return `${m} minute${m !== 1 ? "s" : ""}`;
    if (m === 0) return `${h} hour${h !== 1 ? "s" : ""}`;
    return `${h}h ${m}m`;
}

export default function Create() {
    const { expansions, defaultExpansionId, guildTags, characters, nearbyReports } = usePage().props;

    const [title, setTitle] = useState("");
    const [startTime, setStartTime] = useState("");
    const [endTime, setEndTime] = useState("");
    const [guildTagId, setGuildTagId] = useState("");
    const [selectedExpansionId, setSelectedExpansionId] = useState(defaultExpansionId ?? "");
    const [zoneText, setZoneText] = useState("");
    const [selectedZone, setSelectedZone] = useState(null);
    const [addedCharacters, setAddedCharacters] = useState([]);
    const [characterSearch, setCharacterSearch] = useState("");
    const [lootCouncillorIds, setLootCouncillorIds] = useState([]);
    const [linkedReportIds, setLinkedReportIds] = useState([]);
    const [processing, setProcessing] = useState(false);
    const [errors, setErrors] = useState({});

    const zonesForExpansion = useMemo(() => {
        const expansion = expansions.find((e) => e.id === Number(selectedExpansionId));
        return expansion?.zones ?? [];
    }, [expansions, selectedExpansionId]);

    const addedCharacterIds = new Set(addedCharacters.map((c) => c.id));
    const availableCharacters = characters.filter((c) => !addedCharacterIds.has(c.id));

    const timezoneHelp = useMemo(() => {
        const parisTimezoneName =
            new Intl.DateTimeFormat("en-US", {
                timeZone: "Europe/Paris",
                timeZoneName: "long",
            })
                .formatToParts(new Date())
                .find((p) => p.type === "timeZoneName")?.value ?? "Central European Time";

        const userTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const parisOff = getUtcOffsetMinutes("Europe/Paris");
        const localOff = getUtcOffsetMinutes(userTz);
        const diffMin = localOff - parisOff;
        const isMateriallyDifferent = Math.abs(diffMin) > 30;

        return (
            <>
                <p>
                    Enter times in <strong>{parisTimezoneName}</strong> (server time).
                </p>
                {isMateriallyDifferent && (
                    <p className="mt-1 text-amber-400">
                        Your timezone is {formatOffsetDiff(diffMin)} {diffMin > 0 ? "ahead of" : "behind"} server time.
                        Remember to enter times in server time.
                    </p>
                )}
            </>
        );
    }, []);

    /**
     * Zone autocomplete: getOptionValue returns the zone name.
     * When a zone is selected, onChange(zone.name) fires, and we find the zone object by name.
     */
    const handleZoneChange = (val) => {
        setZoneText(val);
        const matched = zonesForExpansion.find((z) => z.name === val);
        setSelectedZone(matched ?? null);
        if (matched) {
            setErrors((prev) => ({ ...prev, zone_id: null }));
        }
    };

    const handleExpansionChange = (e) => {
        setSelectedExpansionId(Number(e.target.value));
        setZoneText("");
        setSelectedZone(null);
    };

    /**
     * Character autocomplete: getOptionValue returns the character ID as a string.
     * When a character is selected from the dropdown, onChange(String(c.id)) fires.
     * We detect this by checking if val is a positive integer string, then look up the character.
     * If it's just a search string (user typing), we update characterSearch normally.
     */
    const handleCharacterAutocompleteChange = (val) => {
        const numVal = Number(val);
        if (Number.isInteger(numVal) && numVal > 0) {
            const char = availableCharacters.find((c) => c.id === numVal);
            if (char) {
                setAddedCharacters((prev) => [...prev, char]);
                setErrors((prev) => ({ ...prev, character_ids: null }));
            }
            setCharacterSearch("");
        } else {
            setCharacterSearch(val);
        }
    };

    const handleCharacterRemove = (characterId) => {
        setAddedCharacters((prev) => prev.filter((c) => c.id !== characterId));
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        setErrors({});
        setProcessing(true);

        router.post(
            route("raids.reports.store"),
            {
                title,
                start_time: startTime,
                end_time: endTime,
                guild_tag_id: guildTagId || null,
                expansion_id: selectedExpansionId,
                zone_id: selectedZone?.id ?? null,
                character_ids: addedCharacters.map((c) => c.id),
                loot_councillor_ids: lootCouncillorIds,
                linked_report_ids: linkedReportIds,
            },
            {
                onError: (errs) => setErrors(errs),
                onFinish: () => setProcessing(false),
            },
        );
    };

    return (
        <Master title="Create Report">
            <SharedHeader title="Create Report" backgroundClass="bg-illidan" />

            <div className="py-8 text-white">
                <div className="container mx-auto max-w-2xl px-4">
                    <div className="mb-6">
                        <Link
                            href={route("raids.reports.index")}
                            className="inline-flex items-center gap-2 text-sm text-amber-400 hover:text-amber-300 hover:underline"
                        >
                            <Icon icon="arrow-left" style="solid" />
                            Back to Reports
                        </Link>
                    </div>

                    <form onSubmit={handleSubmit} className="space-y-6">
                        {/* Title */}
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-gray-300">
                                Title <span className="text-red-400">*</span>
                            </label>
                            <input
                                type="text"
                                value={title}
                                onChange={(e) => {
                                    setTitle(e.target.value);
                                    setErrors((prev) => ({ ...prev, title: null }));
                                }}
                                placeholder="e.g. Sunday Karazhan"
                                className="w-full rounded border border-amber-600 bg-brown-800 px-4 py-2 text-white placeholder-gray-400 focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500"
                            />
                            <InputError message={errors.title} className="mt-2" />
                        </div>

                        {/* Start time */}
                        <div className="flex flex-col lg:flex-row lg:gap-6">
                            <div className="flex-1">
                                <label className="mb-1.5 block text-sm font-medium text-gray-300">
                                    Start time <span className="text-red-400">*</span>
                                </label>
                                <DateFilterButton
                                    label="Start time"
                                    value={startTime}
                                    onChange={(val) => {
                                        setStartTime(val);
                                        setErrors((prev) => ({ ...prev, start_time: null }));
                                    }}
                                    includeTime
                                    helpText={timezoneHelp}
                                    max="2099-12-31T23:59"
                                />
                                <InputError message={errors.start_time} className="mt-2" />
                            </div>

                            {/* End time */}
                            <div className="flex-1">
                                <label className="mb-1.5 block text-sm font-medium text-gray-300">
                                    End time <span className="text-red-400">*</span>
                                </label>
                                <DateFilterButton
                                    label="End time"
                                    value={endTime}
                                    onChange={(val) => {
                                        setEndTime(val);
                                        setErrors((prev) => ({ ...prev, end_time: null }));
                                    }}
                                    includeTime
                                    helpText={timezoneHelp}
                                    min={startTime || undefined}
                                    max="2099-12-31T23:59"
                                />
                                <InputError message={errors.end_time} className="mt-2" />
                            </div>
                        </div>

                        {/* Guild tag */}
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-gray-300">
                                Warcraft Logs tag <span className="text-red-400">*</span>
                            </label>
                            <select
                                value={guildTagId}
                                onChange={(e) => {
                                    setGuildTagId(e.target.value);
                                    setErrors((prev) => ({ ...prev, guild_tag_id: null }));
                                }}
                                className="w-full rounded border border-amber-600 bg-brown-800 px-4 py-2 text-white focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500"
                            >
                                <option value="">Select a tag…</option>
                                {guildTags.map((tag) => (
                                    <option key={tag.id} value={tag.id}>
                                        {tag.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={errors.guild_tag_id} className="mt-2" />
                        </div>

                        {/* Zone + Expansion */}
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-gray-300">
                                Zone <span className="text-red-400">*</span>
                            </label>
                            <div className="flex gap-3">
                                <select
                                    value={selectedExpansionId}
                                    onChange={handleExpansionChange}
                                    className="w-48 shrink-0 rounded border border-amber-600 bg-brown-800 px-3 py-2 text-sm text-white focus:border-amber-500 focus:outline-none focus:ring-2 focus:ring-amber-500"
                                >
                                    {expansions.map((exp) => (
                                        <option key={exp.id} value={exp.id}>
                                            {exp.name}
                                        </option>
                                    ))}
                                </select>
                                <div className="flex-1">
                                    <Autocomplete
                                        value={zoneText}
                                        onChange={handleZoneChange}
                                        options={zonesForExpansion}
                                        placeholder="Search zones…"
                                        getOptionValue={(z) => z.name}
                                        getSearchableText={(z) => z.name}
                                        renderOption={(z) => z.name}
                                    />
                                </div>
                            </div>
                            <InputError message={errors.zone_id} className="mt-2" />
                            <InputError message={errors.expansion_id} className="mt-1" />
                        </div>

                        {/* Characters */}
                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-gray-300">Characters</label>
                            <Autocomplete
                                value={characterSearch}
                                onChange={handleCharacterAutocompleteChange}
                                options={availableCharacters}
                                placeholder="Search characters…"
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
                                        {c.is_main && (
                                            <span className="rounded bg-amber-600/20 px-1.5 py-0.5 text-xs text-amber-400">
                                                Main
                                            </span>
                                        )}
                                    </span>
                                )}
                            />
                            <InputError message={errors.character_ids} className="mt-2" />

                            {addedCharacters.length > 0 && (
                                <ul className="mt-3 divide-y divide-brown-700 rounded border border-amber-600/30">
                                    {addedCharacters.map((character) => (
                                        <li key={character.id} className="flex items-center justify-between px-4 py-2">
                                            <span className="flex items-center gap-2 text-sm text-white">
                                                {character.playable_class?.icon_url && (
                                                    <img
                                                        src={character.playable_class.icon_url}
                                                        alt={character.playable_class.name}
                                                        className="h-4 w-4 rounded-sm"
                                                    />
                                                )}
                                                {character.name}
                                                {character.is_main && (
                                                    <span className="rounded bg-amber-600/20 px-1.5 py-0.5 text-xs text-amber-400">
                                                        Main
                                                    </span>
                                                )}
                                            </span>
                                            <button
                                                type="button"
                                                onClick={() => handleCharacterRemove(character.id)}
                                                className="rounded p-1 text-gray-500 transition-colors hover:bg-red-700/20 hover:text-red-400"
                                            >
                                                <Icon icon="times" style="solid" className="text-xs" />
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            )}
                        </div>

                        {/* Loot councillors */}
                        <RaidReportLootCouncillors
                            reportId={null}
                            characters={[]}
                            onChange={(ids) => setLootCouncillorIds(ids)}
                        />

                        {/* Linked reports */}
                        <LinkedRaidReports
                            currentReport={null}
                            canManageLinks={true}
                            nearbyReports={nearbyReports}
                            impactedReports={null}
                            onChange={(ids) => setLinkedReportIds(ids)}
                            referenceDate={startTime || null}
                        />

                        {/* Actions */}
                        <div className="flex items-center gap-4 border-t border-amber-600/30 pt-4">
                            <button
                                type="submit"
                                disabled={processing}
                                className={`inline-flex items-center gap-2 rounded-md border border-transparent bg-amber-600 px-5 py-2 text-sm font-semibold uppercase tracking-widest text-white transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 ${processing ? "opacity-25" : ""}`}
                            >
                                <Icon icon="plus" style="solid" />
                                {processing ? "Creating…" : "Create Report"}
                            </button>
                            <Link
                                href={route("raids.reports.index")}
                                className="text-sm text-gray-400 hover:text-white"
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
