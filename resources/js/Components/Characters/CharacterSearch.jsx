import { useMemo, useState } from "react";
import { usePage } from "@inertiajs/react";
import { Combobox, ComboboxButton, ComboboxInput, ComboboxOption, ComboboxOptions } from "@headlessui/react";
import Icon from "@/Components/FontAwesome/Icon";
import IconButton from "@/Components/IconButton";
import Pill from "@/Components/Pill";
import CharacterPortrait from "@/Components/Characters/CharacterPortrait";
import ClassPill from "@/Components/Characters/ClassPill";
import { controlClassName } from "@/Components/FormControls";
import normaliseCharacterName from "@/Helpers/NormaliseCharacterName";
import CharacterPortraitRefresher from "@/Components/Listeners/CharacterPortraitRefresher";

/**
 * A searchable drop-down for picking one character by ID. The search ignores
 * case and diacritics, so "deo" finds "Déo". Once a character is picked, their
 * portrait replaces the search icon and their class follows their name.
 * With `refreshPortrait`, picking a character also refreshes their portrait
 * through the page's optional `character_portrait` prop, which the page's
 * controller shares via the SharesCharacterPortrait concern.
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
    refreshPortrait = false,
}) {
    const [query, setQuery] = useState("");
    const refreshedPortrait = usePage().props.character_portrait;

    const selectedCharacter = value ? (characters.find((character) => character.id === value) ?? null) : null;
    const selectedPortraitCharacter =
        selectedCharacter && refreshedPortrait?.id === selectedCharacter.id && refreshedPortrait.portrait_url
            ? { ...selectedCharacter, portrait_url: refreshedPortrait.portrait_url }
            : selectedCharacter;

    const filtered = useMemo(() => {
        if (!query) {
            return characters;
        }

        const needle = normaliseCharacterName(query);

        return characters.filter((character) => normaliseCharacterName(character.full_name).includes(needle));
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
            {refreshPortrait && selectedCharacter && <CharacterPortraitRefresher characterId={selectedCharacter.id} />}

            <div className="relative">
                <span className="pointer-events-none absolute inset-y-0 left-2 flex items-center">
                    {selectedCharacter ? (
                        <CharacterPortrait character={selectedPortraitCharacter} className="size-7" />
                    ) : (
                        // Font Awesome swaps the <i> for an <svg>, so React must unmount a wrapper it owns instead.
                        <span>
                            <Icon icon="search" style="solid" className="text-gray-400 ml-1.5" />
                        </span>
                    )}
                </span>

                <ComboboxInput
                    id={id}
                    displayValue={(character) => character?.full_name ?? ""}
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
                        <span className="invisible whitespace-pre">{selectedCharacter.full_name}</span>
                        <ClassPill playableClass={selectedCharacter.playable_class} />
                    </span>
                )}

                {!disabled && (query || selectedCharacter) ? (
                    <IconButton
                        icon="times"
                        label="Clear character"
                        onClick={handleClear}
                        className="absolute inset-y-0 right-1 my-auto flex size-8 items-center justify-center rounded text-gray-400 hover:text-white"
                    />
                ) : (
                    <ComboboxButton
                        aria-label="Show characters"
                        className="text-gray-400 absolute inset-y-0 right-1 my-auto flex size-8 items-center justify-center rounded data-hover:text-white"
                    >
                        <Icon icon="chevron-down" style="solid" className="text-xs" />
                    </ComboboxButton>
                )}
            </div>

            <ComboboxOptions
                anchor="bottom start"
                className="border-amber-600 bg-brown-800 z-50 max-h-64 w-(--input-width) overflow-y-auto rounded border py-1 shadow-lg shadow-black/40 [--anchor-gap:4px]"
            >
                {filtered.length === 0 ? (
                    <div className="text-gray-300 px-4 py-2 text-sm">{emptyMessage}</div>
                ) : (
                    filtered.map((character) => {
                        const mainName = character.is_main ? null : character.linked_characters?.[0]?.full_name;

                        return (
                            <ComboboxOption
                                key={character.id}
                                value={character}
                                className="data-focus:bg-brown-700 flex cursor-pointer items-center gap-3 px-3 py-1.5 text-sm text-white"
                            >
                                <CharacterPortrait character={character} />
                                <span className="truncate">{character.full_name}</span>
                                <ClassPill playableClass={character.playable_class} />
                                {mainName && (
                                    <Pill bgColor="bg-brown-700" textColor="text-gray-300">
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
