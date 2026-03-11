import { useState, useMemo, useRef, useEffect } from "react";
import { router, usePage } from "@inertiajs/react";
import axios from "axios";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import DateFilterButton from "@/Components/DateFilterButton";
import MarkdownEditor from "@/Components/MarkdownEditor";
import InputError from "@/Components/InputError";
import Icon from "@/Components/FontAwesome/Icon";

const ALLOWED_FORMATS = ["bold", "italic", "underline", "bulletList", "numberedList"];

function CharacterSearch({ characters, value, onChange, error }) {
    const [search, setSearch] = useState("");
    const [isOpen, setIsOpen] = useState(false);
    const containerRef = useRef(null);

    const selectedCharacter = value ? characters.find((c) => c.id === value) : null;
    const displayValue = selectedCharacter ? selectedCharacter.name : search;

    const filtered = useMemo(() => {
        if (!search || selectedCharacter) return characters;
        return characters.filter((c) => c.name.toLowerCase().includes(search.toLowerCase()));
    }, [characters, search, selectedCharacter]);

    useEffect(() => {
        const handleClickOutside = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setIsOpen(false);
            }
        };
        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    function handleInputChange(e) {
        setSearch(e.target.value);
        onChange(null);
        setIsOpen(true);
    }

    function handleSelect(character) {
        onChange(character.id);
        setSearch(character.name);
        setIsOpen(false);
    }

    function handleClear() {
        setSearch("");
        onChange(null);
        setIsOpen(false);
    }

    function handleFocus() {
        if (!selectedCharacter) {
            setIsOpen(true);
        }
    }

    return (
        <div ref={containerRef} className="relative">
            <div className="relative">
                <Icon
                    icon="search"
                    style="solid"
                    className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"
                />
                <input
                    type="text"
                    value={selectedCharacter ? selectedCharacter.name : search}
                    onChange={handleInputChange}
                    onFocus={handleFocus}
                    placeholder="Search by character name..."
                    className="w-full rounded border border-amber-600 bg-brown-800 py-2 pl-10 pr-10 text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
                />
                {(search || selectedCharacter) && (
                    <button
                        type="button"
                        onClick={handleClear}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white"
                    >
                        <Icon icon="times" style="solid" />
                    </button>
                )}
            </div>

            {isOpen && filtered.length > 0 && (
                <ul className="absolute z-50 mt-1 max-h-48 w-full overflow-y-auto rounded border border-amber-600 bg-brown-800 shadow-lg">
                    {filtered.map((character) => (
                        <li key={character.id}>
                            <button
                                type="button"
                                onClick={() => handleSelect(character)}
                                className="w-full px-4 py-2 text-left text-sm text-white transition-colors hover:bg-brown-700"
                            >
                                {character.name}
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <InputError message={error} className="mt-2" />
        </div>
    );
}

export default function Create() {
    const { characters, plannedAbsence: rawPlannedAbsence = null, action } = usePage().props;
    const plannedAbsence = rawPlannedAbsence?.data ?? null;
    const isEditing = plannedAbsence !== null;

    const [characterId, setCharacterId] = useState(plannedAbsence?.character?.id ?? null);
    const [startDate, setStartDate] = useState(plannedAbsence?.start_date ?? "");
    const [endDate, setEndDate] = useState(plannedAbsence?.end_date ?? "");
    const [reason, setReason] = useState(plannedAbsence?.reason ?? "");
    const [processing, setProcessing] = useState(false);

    const [errors, setErrors] = useState({});
    const [serverError, setServerError] = useState(null);
    const [multipleCharacters, setMultipleCharacters] = useState(null);

    function submit(e) {
        e.preventDefault();

        setErrors({});
        setServerError(null);
        setMultipleCharacters(null);
        setProcessing(true);

        if (isEditing) {
            axios
                .patch(action, {
                    character: characterId,
                    start_date: startDate,
                    end_date: endDate || null,
                    reason,
                })
                .then(() => {
                    router.visit(route("raids.absences.index"));
                })
                .catch((err) => {
                    const status = err.response?.status;
                    const data = err.response?.data;

                    if (status === 422) {
                        const fieldErrors = {};
                        Object.entries(data.errors ?? {}).forEach(([field, messages]) => {
                            fieldErrors[field] = Array.isArray(messages) ? messages[0] : messages;
                        });
                        setErrors(fieldErrors);
                    } else if (status === 400) {
                        setServerError({
                            message: data.message,
                            suggestion: data.suggestion ?? null,
                        });
                    } else if (status === 300) {
                        setMultipleCharacters(data.characters ?? []);
                        setServerError({ message: data.message });
                    } else {
                        setServerError({ message: "An unexpected error occurred. Please try again." });
                    }
                })
                .finally(() => {
                    setProcessing(false);
                });
        } else {
            const stopInvalidListener = router.on("invalid", (event) => {
                event.preventDefault();
                stopInvalidListener();

                const { status, data } = event.detail.response;

                if (status === 400) {
                    setServerError({
                        message: data.message,
                        suggestion: data.suggestion ?? null,
                    });
                } else if (status === 300) {
                    setMultipleCharacters(data.characters ?? []);
                    setServerError({ message: data.message });
                } else {
                    setServerError({ message: "An unexpected error occurred. Please try again." });
                }

                setProcessing(false);
            });

            router.post(
                action,
                {
                    character: characterId,
                    start_date: startDate,
                    end_date: endDate || null,
                    reason,
                },
                {
                    onError: (errors) => setErrors(errors),
                    onFinish: () => {
                        stopInvalidListener();
                        setProcessing(false);
                    },
                },
            );
        }
    }

    function selectDisambiguatedCharacter(character) {
        setCharacterId(character.id);
        setMultipleCharacters(null);
        setServerError(null);
    }

    const pageTitle = isEditing ? "Edit Planned Absence" : "Log Planned Absence";

    return (
        <Master title={pageTitle}>
            <SharedHeader title={pageTitle} backgroundClass="bg-illidan" />

            <div className="py-8 text-white">
                <div className="container mx-auto max-w-lg px-4">
                    <form onSubmit={submit} className="flex flex-col gap-6">
                        {serverError && (
                            <div className="rounded border border-red-600 bg-red-900/30 px-4 py-3 text-sm text-red-300">
                                <p>{serverError.message}</p>
                                {serverError.suggestion && (
                                    <p className="mt-1 text-red-400">
                                        Did you mean: <strong>{serverError.suggestion}</strong>?
                                    </p>
                                )}
                            </div>
                        )}

                        {multipleCharacters && (
                            <div className="rounded border border-amber-600 bg-brown-800/50 px-4 py-3">
                                <p className="mb-3 text-sm text-amber-300">Multiple characters matched. Please select one:</p>
                                <ul className="flex flex-col gap-1">
                                    {multipleCharacters.map((c) => (
                                        <li key={c.id}>
                                            <button
                                                type="button"
                                                onClick={() => selectDisambiguatedCharacter(c)}
                                                className="w-full rounded px-3 py-2 text-left text-sm text-white transition-colors hover:bg-brown-700"
                                            >
                                                {c.name}
                                            </button>
                                        </li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-gray-300">
                                Character
                            </label>
                            <CharacterSearch
                                characters={characters.data}
                                value={characterId}
                                onChange={(id) => {
                                    setCharacterId(id);
                                    if (id) {
                                        setErrors((prev) => ({ ...prev, character: null }));
                                    }
                                }}
                                error={errors.character}
                            />
                        </div>

                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-gray-300">
                                Start date
                            </label>
                            <DateFilterButton
                                label="Start date"
                                value={startDate}
                                onChange={(val) => {
                                    setStartDate(val);
                                    setErrors((prev) => ({ ...prev, start_date: null }));
                                }}
                                max="2099-12-31"
                            />
                            <InputError message={errors.start_date} className="mt-2" />
                        </div>

                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-gray-300">
                                End date <span className="text-gray-500">(optional)</span>
                            </label>
                            <DateFilterButton
                                label="End date"
                                value={endDate}
                                onChange={(val) => {
                                    setEndDate(val);
                                    setErrors((prev) => ({ ...prev, end_date: null }));
                                }}
                                min={startDate}
                                max="2099-12-31"
                            />
                            <InputError message={errors.end_date} className="mt-2" />
                        </div>

                        <div>
                            <label className="mb-1.5 block text-sm font-medium text-gray-300">
                                Reason
                            </label>
                            <MarkdownEditor
                                value={reason}
                                onChange={(val) => {
                                    setReason(val);
                                    if (val.trim()) {
                                        setErrors((prev) => ({ ...prev, reason: null }));
                                    }
                                }}
                                allowedFormats={ALLOWED_FORMATS}
                                rows={5}
                                error={errors.reason}
                            />
                        </div>

                        <div className="flex items-center gap-3">
                            <button
                                type="submit"
                                disabled={processing}
                                className={`inline-flex items-center gap-2 rounded-md border border-transparent bg-amber-600 px-5 py-2 text-sm font-semibold uppercase tracking-widest text-white transition hover:bg-amber-700 focus:outline-none focus:ring-2 focus:ring-amber-500 focus:ring-offset-2 ${processing && "opacity-25"}`}
                            >
                                <Icon icon="calendar-plus" style="solid" />
                                {processing ? "Saving..." : isEditing ? "Save Changes" : "Log Absence"}
                            </button>
                            <a
                                href={route("raids.absences.index")}
                                className="text-sm text-gray-400 hover:text-white"
                            >
                                Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>
        </Master>
    );
}
