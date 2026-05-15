import { useEffect, useMemo, useRef, useState } from "react";
import { router, usePage } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import Modal from "@/Components/Modal";
import TargetMarker from "@/Components/TargetMarker";
import Tooltip from "@/Components/Tooltip";
import usePermission from "@/Hooks/Permissions";
import MODEL_TYPES from "@/Helpers/AssignmentModelTypes";

// ─── Constants ────────────────────────────────────────────────────────────────

const AFFECT_TYPES = ["Curse", "Disease", "Magic", "Poison", "Physical"];

// ─── Module-level fetch flag (shared across all cell editors on the page) ─────

let assignmentOptionsFetched = false;

export function resetAssignmentOptionsFetched() {
    assignmentOptionsFetched = false;
}

// ─── Option builder ───────────────────────────────────────────────────────────

function buildOptions({ characters, playableClasses, targetMarkers, spells, groups, query, defaultIconUrl }) {
    const q = query.toLowerCase();

    const targetMarkerOptions = [];
    (targetMarkers ?? []).forEach((m) => {
        if (!q || m.name.toLowerCase().includes(q)) {
            targetMarkerOptions.push({
                type: "target_marker",
                label: m.name,
                slug: m.slug,
                value: { left_type: MODEL_TYPES.TARGET_MARKER, left_value: m.slug },
                side: { type: "target_marker", data: m },
            });
        }
    });

    const characterOptions = [];
    (characters ?? []).forEach((c) => {
        if (!q || c.name.toLowerCase().includes(q)) {
            const sublabel = c.rank ? <span className={`text-guild-rank-${c.rank?.slug}`}>{c.rank?.name}</span> : "";
            characterOptions.push({
                type: "character",
                label: c.name,
                sublabel,
                iconUrl: c.playable_class?.icon_url ?? defaultIconUrl,
                value: { left_type: MODEL_TYPES.CHARACTER, left_value: String(c.id) },
                side: { type: "character", data: c },
            });
        }
    });

    const compGroupOptions = [];
    const maxGroup = Math.max(0, ...(groups ?? []).map((g) => g.group_number));
    const groupQueryResult = (() => {
        if (!query) return null;
        if (/^\d+$/.test(query)) {
            const n = parseInt(query, 10);
            if (n >= 1 && n <= maxGroup) return `Group ${n}`;
        }
        const rangeMatch = query.match(/^(\d+)-(\d+)$/);
        if (rangeMatch) {
            const [, left, right] = rangeMatch.map(Number);
            if (left >= 1 && right <= maxGroup && left < right) return `Groups ${query}`;
        }
        if (/^\d+(\s*,\s*\d+)+$/.test(query)) {
            const nums = query.split(",").map((s) => parseInt(s.trim(), 10));
            if (nums.every((n) => n >= 1 && n <= maxGroup)) {
                const last = nums.pop();
                const label =
                    nums.length === 0
                        ? `Group ${last}`
                        : nums.length === 1
                          ? `Groups ${nums[0]} and ${last}`
                          : `Groups ${nums.join(", ")}, and ${last}`;
                return label;
            }
        }
        return null;
    })();
    if (groupQueryResult !== null) {
        compGroupOptions.push({
            type: "groups",
            label: groupQueryResult,
            value: { left_type: null, left_value: query },
            side: { type: "groups", data: query },
        });
    }

    const playableClassOptions = [];
    (playableClasses ?? []).forEach((playableClass) => {
        if (!q || playableClass.name.toLowerCase().includes(q)) {
            playableClassOptions.push({
                type: "playable_class",
                label: playableClass.name,
                iconUrl: playableClass.icon_url ?? defaultIconUrl,
                value: { left_type: MODEL_TYPES.PLAYABLE_CLASS, left_value: String(playableClass.id) },
                side: { type: "playable_class", data: playableClass },
            });
        }
    });

    const spellOptions = [];
    (spells ?? []).forEach((s) => {
        if (!q || s.name.toLowerCase().includes(q)) {
            spellOptions.push({
                type: "spell",
                label: s.name,
                value: { left_type: MODEL_TYPES.SPELL, left_value: String(s.id) },
                spell: s,
                side: { type: "spell", data: s },
            });
        }
    });

    return { targetMarkerOptions, characterOptions, compGroupOptions, playableClassOptions, spellOptions };
}

// ─── Define new spell modal ───────────────────────────────────────────────────

function DefineSpellModal({ initialName = "", onClose, onCreated }) {
    const [formData, setFormData] = useState({ name: initialName, type: "Magic" });
    const nameInputRef = useRef(null);
    const [submitting, setSubmitting] = useState(false);
    const [errors, setErrors] = useState({});

    useEffect(() => {
        nameInputRef.current?.focus();
    }, []);

    const handleSubmit = (e) => {
        e.preventDefault();
        setSubmitting(true);
        setErrors({});

        fetch(route("api.spells.store"), {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
                Accept: "application/json",
                "X-CSRF-TOKEN": document.querySelector('meta[name="csrf-token"]')?.content ?? "",
                "X-Requested-With": "XMLHttpRequest",
            },
            body: JSON.stringify(formData),
        })
            .then((r) => {
                if (!r.ok) {
                    return r.json().then((data) => {
                        setErrors(data.errors ?? {});
                        setSubmitting(false);
                    });
                }
                return r.json().then((spell) => {
                    router.reload({ only: ["spells"] });
                    onCreated(spell);
                });
            })
            .catch(() => setSubmitting(false));
    };

    return (
        <Modal show onClose={onClose} maxWidth="2xl">
            <div className="p-6">
                <div className="mb-4 flex items-center justify-between">
                    <h3 className="font-semibold text-amber-400">Define New Spell</h3>
                    <button type="button" onClick={onClose} className="text-brown-400 hover:text-white">
                        <Icon icon="times" style="solid" />
                    </button>
                </div>

                <form onSubmit={handleSubmit} className="space-y-4">
                    <div>
                        <label className="mb-1 block text-xs font-medium uppercase tracking-wide text-brown-400">
                            Name
                        </label>
                        <input
                            ref={nameInputRef}
                            type="text"
                            value={formData.name}
                            onChange={(e) => setFormData((d) => ({ ...d, name: e.target.value }))}
                            className="w-full rounded border border-brown-600 bg-brown-800 px-3 py-2 text-sm text-white focus:border-amber-500 focus:outline-none"
                            required
                        />
                        {errors.name && <p className="mt-1 text-xs text-red-400">{errors.name}</p>}
                    </div>

                    <div>
                        <label className="mb-1 block text-xs font-medium uppercase tracking-wide text-brown-400">
                            Type
                        </label>
                        <select
                            value={formData.type}
                            onChange={(e) => setFormData((d) => ({ ...d, type: e.target.value }))}
                            className={`w-full rounded border border-${formData.type ? "affect-" + formData.type.toLowerCase() : "brown-600"} bg-${formData.type ? "affect-" + formData.type.toLowerCase() + "/20" : "brown-800"} px-3 py-2 text-sm text-white focus:border-amber-500 focus:outline-none`}
                        >
                            {AFFECT_TYPES.map((t) => (
                                <option key={t} value={t}>
                                    {t}
                                </option>
                            ))}
                        </select>
                    </div>

                    <div className="flex justify-end gap-3 pt-2">
                        <button type="button" onClick={onClose} className="text-sm text-brown-400 hover:text-white">
                            Cancel
                        </button>
                        <button
                            type="submit"
                            disabled={submitting || !formData.name.trim()}
                            className="rounded bg-amber-600 px-4 py-2 text-sm font-medium text-white transition-colors hover:bg-amber-700 disabled:opacity-40"
                        >
                            {submitting ? "Creating…" : "Create Spell"}
                        </button>
                    </div>
                </form>
            </div>
        </Modal>
    );
}

// ─── Autocomplete cell ────────────────────────────────────────────────────────

export default function AssignmentCellEditor({
    initialLabel,
    initialIconUrl,
    initialSlug,
    colorClass,
    textClass,
    targetMarkers,
    onSelect,
}) {
    const { characters, spells, playableClasses, event, questionMarkIconUrl } = usePage().props;
    const groups = event?.composition?.groups ?? [];
    const defaultIconUrl = questionMarkIconUrl ?? null;
    const canCreateSpells = usePermission("edit-datasets");
    const [query, setQuery] = useState(initialLabel ?? "");
    const [open, setOpen] = useState(false);
    const [showDefineSpell, setShowDefineSpell] = useState(false);
    const [dataLoading, setDataLoading] = useState(false);
    const inputRef = useRef(null);
    const dropdownRef = useRef(null);
    const caretRef = useRef(null);

    const openDropdown = () => {
        setOpen(true);
        const needsCharacters = characters === undefined;
        const needsPlayableClasses = playableClasses === undefined;
        const needsSpells = spells === undefined;
        if ((needsCharacters || needsPlayableClasses || needsSpells) && !assignmentOptionsFetched) {
            assignmentOptionsFetched = true;
            setDataLoading(true);
            const only = [
                ...(needsCharacters ? ["characters"] : []),
                ...(needsPlayableClasses ? ["playableClasses"] : []),
                ...(needsSpells ? ["spells"] : []),
            ];
            router.reload({
                only,
                onFinish: () => setDataLoading(false),
            });
        }
    };

    const [committedIconUrl, setCommittedIconUrl] = useState(initialIconUrl ?? null);
    const [committedSlug, setCommittedSlug] = useState(initialSlug ?? null);

    const { targetMarkerOptions, characterOptions, compGroupOptions, playableClassOptions, spellOptions } = useMemo(
        () => buildOptions({ characters, playableClasses, targetMarkers, spells, groups, query, defaultIconUrl }),
        [characters, playableClasses, targetMarkers, spells, groups, query, defaultIconUrl],
    );

    useEffect(() => {
        const handler = (e) => {
            if (
                !dropdownRef.current?.contains(e.target) &&
                !inputRef.current?.contains(e.target) &&
                !caretRef.current?.contains(e.target)
            ) {
                setOpen(false);
            }
        };
        document.addEventListener("mousedown", handler);
        return () => document.removeEventListener("mousedown", handler);
    }, []);

    const handleSelect = (option) => {
        setQuery(option.label);
        setCommittedIconUrl(option.iconUrl ?? null);
        setCommittedSlug(option.slug ?? null);
        setOpen(false);
        onSelect({
            left_type: option.value.left_type,
            left_value: option.value.left_value,
            side: option.side ?? null,
        });
    };

    const handleUseRaw = () => {
        setCommittedIconUrl(null);
        setCommittedSlug(null);
        setOpen(false);
        onSelect({ left_type: null, left_value: query, side: null });
    };

    const handleClear = () => {
        setQuery("");
        setCommittedIconUrl(null);
        setCommittedSlug(null);
        openDropdown();
    };

    const displayIconUrl = open ? null : committedIconUrl;
    const displaySlug = open ? null : committedSlug;

    const renderOptionIcon = (opt) => {
        if (opt.type === "target_marker") {
            return (
                <span className="flex h-6 w-6 shrink-0 items-center justify-center">
                    <TargetMarker marker={opt.slug} />
                </span>
            );
        }
        if (opt.iconUrl) {
            return <img src={opt.iconUrl} alt="" className="h-6 w-6 shrink-0 rounded-sm" />;
        }
        return <span className="h-6 w-6 shrink-0" />;
    };

    return (
        <td className={`relative w-1/2 border-r border-brown-700/50 p-0 last:border-r-0 ${colorClass}`}>
            <div className="relative flex items-center">
                {(displayIconUrl || displaySlug) && (
                    <span className="pointer-events-none absolute left-2 mr-2 flex h-6 w-6 items-center justify-center">
                        {displaySlug ? (
                            <TargetMarker marker={displaySlug} />
                        ) : (
                            <img src={displayIconUrl} alt="" className="h-6 w-6 rounded-sm" />
                        )}
                    </span>
                )}
                <input
                    ref={inputRef}
                    type="text"
                    value={query}
                    onChange={(e) => {
                        setQuery(e.target.value);
                        setCommittedIconUrl(null);
                        setCommittedSlug(null);
                        openDropdown();
                    }}
                    onFocus={openDropdown}
                    placeholder="Type to search…"
                    className={`w-full bg-transparent py-2.5 pr-7 text-sm placeholder-brown-600 transition-colors focus:bg-brown-800/60 focus:outline-none ${textClass} ${
                        displayIconUrl || displaySlug ? "pl-10" : "pl-3"
                    }`}
                />
                <button
                    ref={caretRef}
                    type="button"
                    tabIndex={-1}
                    onMouseDown={(e) => {
                        e.preventDefault();
                        if (open) {
                            handleClear();
                        } else {
                            openDropdown();
                        }
                        inputRef.current?.focus();
                    }}
                    className="absolute right-2 flex h-full items-center text-brown-800 hover:text-primary"
                >
                    <span className={open ? "hidden" : ""}>
                        <Icon icon="caret-down" style="solid" className="text-sm" />
                    </span>
                    <span className={open ? "" : "hidden"}>
                        <Tooltip text="Clear content">
                            <Icon icon="backspace" style="solid" className="text-sm" />
                        </Tooltip>
                    </span>
                </button>
            </div>

            {open && (
                <div
                    ref={dropdownRef}
                    className="absolute left-0 top-full z-40 max-h-60 w-72 overflow-auto rounded-b border border-t-0 border-brown-600 bg-brown-900 shadow-xl"
                >
                    {[
                        { label: "Target Markers", options: targetMarkerOptions },
                        { label: "Characters", options: characterOptions },
                        { label: "Groups", options: compGroupOptions },
                        { label: "Playable Classes", options: playableClassOptions },
                        { label: "Spells", options: spellOptions },
                    ].map(({ label, options: groupOpts }) =>
                        groupOpts.length > 0 ? (
                            <div key={label}>
                                <div className="select-none px-3 py-1 text-[10px] font-semibold uppercase tracking-widest text-brown-500">
                                    {label}
                                </div>
                                {groupOpts.map((opt, i) => (
                                    <button
                                        key={i}
                                        type="button"
                                        onMouseDown={(e) => {
                                            e.preventDefault();
                                            handleSelect(opt);
                                        }}
                                        className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm text-brown-200 hover:bg-brown-700"
                                    >
                                        {renderOptionIcon(opt)}
                                        <span className="flex-1 truncate">{opt.label}</span>
                                        {opt.sublabel && <span className="text-xs text-brown-500">{opt.sublabel}</span>}
                                    </button>
                                ))}
                            </div>
                        ) : null,
                    )}

                    {canCreateSpells && query.length > 0 && (
                        <button
                            type="button"
                            onMouseDown={(e) => {
                                e.preventDefault();
                                setOpen(false);
                                setShowDefineSpell(true);
                            }}
                            className="flex w-full items-center gap-2 border-t border-brown-700 px-3 py-2 text-left text-sm text-amber-400 hover:bg-brown-700"
                        >
                            <Icon icon="plus" style="solid" className="text-xs" />
                            Define a new spell
                        </button>
                    )}

                    {query.length > 0 && (
                        <button
                            type="button"
                            onMouseDown={(e) => {
                                e.preventDefault();
                                handleUseRaw();
                            }}
                            className="flex w-full items-center gap-2 border-t border-brown-700 px-3 py-2 text-left text-sm text-brown-400 hover:bg-brown-700"
                        >
                            <Icon icon="pen" style="solid" className="text-xs" />
                            Use &ldquo;{query}&rdquo;
                        </button>
                    )}

                    {dataLoading && (
                        <p className="flex items-center gap-2 px-3 py-2 text-sm text-brown-500">
                            <Icon icon="spinner" style="solid" className="fa-spin text-xs" />
                            Loading…
                        </p>
                    )}

                    {!dataLoading &&
                        characterOptions.length === 0 &&
                        targetMarkerOptions.length === 0 &&
                        spellOptions.length === 0 &&
                        query.length === 0 && (
                            <p className="px-3 py-2 text-sm text-brown-500">Start typing to search…</p>
                        )}
                </div>
            )}

            {showDefineSpell && (
                <DefineSpellModal
                    initialName={query}
                    onClose={() => setShowDefineSpell(false)}
                    onCreated={(spell) => {
                        setShowDefineSpell(false);
                        setQuery(spell.name);
                        setCommittedIconUrl(spell.icon ?? null);
                        setCommittedSlug(null);
                        onSelect({
                            left_type: MODEL_TYPES.SPELL,
                            left_value: String(spell.id),
                            side: { type: "spell", data: spell },
                        });
                    }}
                />
            )}
        </td>
    );
}
