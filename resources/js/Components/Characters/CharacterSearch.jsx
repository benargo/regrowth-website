import { useMemo, useState } from "react";
import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions } from "@headlessui/react";
import Icon from "@/Components/FontAwesome/Icon";
import Pill from "@/Components/Pill";
import CharacterPortrait from "@/Components/Characters/CharacterPortrait";
import ClassPill from "@/Components/Characters/ClassPill";
import { controlClassName } from "@/Components/FormControls";
import normaliseCharacterName from "@/Helpers/NormaliseCharacterName";

/**
 * A searchable drop-down for picking one character by ID. The search ignores
 * case and diacritics, so "deo" finds "Déo". Once a character is picked, their
 * portrait replaces the search icon and their class follows their name.
 * Put it in a FormRow, which labels it and shows any error.
 */
export default function CharacterSearch({
    characters,
    value,
    onChange,
    invalid = false,
    id,
    name,
    disabled = false,
    emptyMessage = "No characters match your search.",
}) {
    const [query, setQuery] = useState("");

    const selectedCharacter = value ? (characters.find((character) => character.id === value) ?? null) : null;

    const filtered = useMemo(() => {
        if (!query) {
            return characters;
        }

        const needle = normaliseCharacterName(query);

        return characters.filter((character) => normaliseCharacterName(character.name).includes(needle));
    }, [characters, query]);

    function handleChange(character) {
        onChange(character?.id ?? null);
        setQuery("");
    }

    function handleQueryChange(event) {
        setQuery(event.target.value);

        if (selectedCharacter) {
            onChange(null);
        }
    }

    function handleClear() {
        setQuery("");
        onChange(null);
    }

    return (
        <Combobox
            value={selectedCharacter}
            onChange={handleChange}
            onClose={() => setQuery("")}
            by="id"
            name={name}
            disabled={disabled}
            invalid={invalid}
            immediate
        >
            <div className="relative">
                <span className="pointer-events-none absolute inset-y-0 left-2 flex items-center">
                    {selectedCharacter ? (
                        <CharacterPortrait character={selectedCharacter} className="size-7" />
                    ) : (
                        // Font Awesome swaps the <i> for an <svg>, so React must unmount a wrapper it owns instead.
                        <span>
                            <Icon icon="search" style="solid" className="text-secondary-400 ml-1.5" />
                        </span>
                    )}
                </span>

                <ComboboxInput
                    id={id}
                    displayValue={(character) => character?.name ?? ""}
                    onChange={handleQueryChange}
                    placeholder="Search by character name"
                    autoComplete="off"
                    spellCheck={false}
                    className={`${controlClassName} pr-10 pl-11 data-disabled:cursor-not-allowed`}
                />

                {/* An invisible copy of the name pushes the class pill to sit just after the input's text. */}
                {selectedCharacter && (
                    <span
                        aria-hidden="true"
                        className="pointer-events-none absolute inset-y-0 right-10 left-[calc(2.75rem+1px)] flex items-center gap-2 overflow-hidden"
                    >
                        <span className="invisible whitespace-pre">{selectedCharacter.name}</span>
                        <ClassPill playableClass={selectedCharacter.playable_class} />
                    </span>
                )}

                {!disabled && (query || selectedCharacter) ? (
                    <button
                        type="button"
                        onClick={handleClear}
                        aria-label="Clear character"
                        className="text-secondary-400 focus-visible:outline-ink-400 absolute inset-y-0 right-1 my-auto flex size-8 items-center justify-center rounded hover:text-white focus-visible:outline-2"
                    >
                        <Icon icon="times" style="solid" />
                    </button>
                ) : (
                    <ComboboxButton
                        aria-label="Show characters"
                        className="text-secondary-400 absolute inset-y-0 right-1 my-auto flex size-8 items-center justify-center rounded data-hover:text-white"
                    >
                        <Icon icon="chevron-down" style="solid" className="text-xs" />
                    </ComboboxButton>
                )}
            </div>

            <ComboboxOptions
                anchor="bottom start"
                className="border-ink-600 bg-ground-800 z-50 max-h-64 w-(--input-width) overflow-y-auto rounded border py-1 shadow-lg shadow-black/40 [--anchor-gap:4px]"
            >
                {filtered.length === 0 ? (
                    <div className="text-secondary-300 px-4 py-2 text-sm">{emptyMessage}</div>
                ) : (
                    filtered.map((character) => {
                        const mainName = character.is_main ? null : character.linked_characters?.[0]?.name;

                        return (
                            <ComboboxOption
                                key={character.id}
                                value={character}
                                className="data-focus:bg-ground-700 flex cursor-pointer items-center gap-3 px-3 py-1.5 text-sm text-white"
                            >
                                <CharacterPortrait character={character} />
                                <span className="truncate">{character.name}</span>
                                <ClassPill playableClass={character.playable_class} />
                                {mainName && (
                                    <Pill bgColor="bg-ground-700" textColor="text-secondary-300">
                                        alt of {mainName}
                                    </Pill>
                                )}
                            </ComboboxOption>
                        );
                    })
                )}
            </ComboboxOptions>
        </Combobox>
    );
}
