import { useState, useMemo, useRef, useEffect } from "react";
import { Link, router, usePage } from "@inertiajs/react";
import usePermission from "@/Hooks/Permissions";
import axios from "axios";
import Master from "@/Layouts/Master";
import Alert from "@/Components/Alert";
import SharedHeader from "@/Components/SharedHeader";
import DateFilterButton from "@/Components/DateFilterButton";
import DiscordUserSearch from "@/Components/DiscordUserSearch";
import MarkdownEditor from "@/Components/MarkdownEditor";
import InputError from "@/Components/InputError";
import Icon from "@/Components/FontAwesome/Icon";

const ALLOWED_FORMATS = ["bold", "italic", "underline", "bulletList", "numberedList"];

function CharacterSearch({ characters, value, onChange, error, disabled = false }) {
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
                <Icon icon="search" style="solid" className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
                <input
                    type="text"
                    value={selectedCharacter ? selectedCharacter.name : search}
                    onChange={disabled ? undefined : handleInputChange}
                    onFocus={disabled ? undefined : handleFocus}
                    placeholder="Search by character name..."
                    disabled={disabled}
                    className={`w-full rounded border border-amber-600 bg-brown-800 py-2 pl-10 pr-10 text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-amber-500 ${disabled ? "cursor-not-allowed opacity-75" : ""}`}
                />
                {!disabled && (search || selectedCharacter) && (
                    <button
                        type="button"
                        onClick={handleClear}
                        className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white"
                    >
                        <Icon icon="times" style="solid" />
                    </button>
                )}
            </div>

            {!disabled && isOpen && filtered.length > 0 && (
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

export default function Form() {
    const {
        auth,
        characters,
        planned_absence: plannedAbsence = null,
        resolved_character: resolvedCharacter = null,
        action,
    } = usePage().props;

    const isEditing = plannedAbsence !== null;
    const canViewAny = usePermission("view-planned-absences");
    const canAssignOtherUser = usePermission("manage-planned-absences");
    const canBackdate = usePermission("manage-planned-absences");

    const [characterId, setCharacterId] = useState(plannedAbsence?.character?.id ?? resolvedCharacter?.id ?? null);
    const isCharacterLocked = !canAssignOtherUser && resolvedCharacter !== null;
    const [userId, setUserId] = useState(plannedAbsence?.user?.id ?? null);

    function handleUserSelect(member) {
        setUserId(member?.id ?? null);

        if (!member?.nickname || characterId) {
            return;
        }

        const matched = characters.find((c) => c.name.toLowerCase() === member.nickname.toLowerCase());

        if (matched) {
            setCharacterId(matched.id);
        }
    }
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

        const effectiveUserId = canAssignOtherUser ? userId || undefined : auth.user.id;

        if (isEditing) {
            axios
                .patch(action, {
                    character: characterId,
                    user: effectiveUserId,
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
                    user: effectiveUserId,
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
                        {!canBackdate && (
                            <Alert type="info">
                                Please{" "}
                                <Link
                                    href="https://discord.com/channels/829020506907869214/1011331714376794233"
                                    className="underline"
                                >
                                    create a ticket on Discord
                                </Link>{" "}
                                if you need to record an absence that started in the past.
                            </Alert>
                        )}

                        {serverError && (
                            <div className="text-md rounded border border-red-600 bg-red-900/30 px-4 py-3 text-red-300">
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
                                <p className="text-md mb-3 text-amber-300">
                                    Multiple characters matched. Please select one:
                                </p>
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

                        {canAssignOtherUser && (
                            <div>
                                <label className="text-md mb-1.5 block font-medium text-gray-300">Discord User</label>
                                <DiscordUserSearch value={userId} onSelect={handleUserSelect} error={errors.user} />
                            </div>
                        )}

                        <div>
                            <label className="text-md mb-1.5 block font-medium text-gray-300">Character</label>
                            <CharacterSearch
                                characters={characters}
                                value={characterId}
                                onChange={(id) => {
                                    setCharacterId(id);
                                    if (id) {
                                        setErrors((prev) => ({ ...prev, character: null }));
                                    }
                                }}
                                error={errors.character}
                                disabled={isCharacterLocked}
                            />
                        </div>

                        <div>
                            <label className="text-md mb-1.5 block font-medium text-gray-300">Start date</label>
                            <DateFilterButton
                                label="Start"
                                value={startDate}
                                onChange={(val) => {
                                    setStartDate(val);
                                    setErrors((prev) => ({ ...prev, start_date: null }));
                                }}
                                min={canBackdate ? undefined : new Date().toISOString().split("T")[0]}
                                max="2099-12-31"
                                helpText="This is the first date of the planned absence."
                            />
                            <InputError message={errors.start_date} className="mt-2" />
                        </div>

                        <div>
                            <label className="text-md mb-1.5 block font-medium text-gray-300">
                                End date <span className="text-gray-500">(optional)</span>
                            </label>
                            <DateFilterButton
                                label="End"
                                value={endDate}
                                onChange={(val) => {
                                    setEndDate(val);
                                    setErrors((prev) => ({ ...prev, end_date: null }));
                                }}
                                min={startDate}
                                max="2099-12-31"
                                helpText={
                                    <>
                                        <p>This is the last date of the planned absence.</p>
                                        <p>
                                            If you are unsure, you can leave this blank and it will show as ongoing.
                                            Make sure it is updated once the absence ends.
                                        </p>
                                    </>
                                }
                            />
                            <InputError message={errors.end_date} className="mt-2" />
                        </div>

                        <div>
                            <label className="text-md mb-1.5 block font-medium text-gray-300">Reason</label>
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
                            {canViewAny ? (
                                <Link
                                    href={route("raids.absences.index")}
                                    className="text-sm text-gray-400 hover:text-white"
                                >
                                    Cancel
                                </Link>
                            ) : (
                                <Link href={route("account.index")} className="text-sm text-gray-400 hover:text-white">
                                    Cancel
                                </Link>
                            )}
                        </div>
                    </form>
                </div>
            </div>
        </Master>
    );
}
