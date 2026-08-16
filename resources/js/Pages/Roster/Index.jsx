import { useMemo } from "react";
import LevelRangeFilter from "@/Components/LevelRangeFilter";
import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import FilterDropdown from "@/Components/FilterDropdown";
import ToggleFilter from "@/Components/ToggleFilter";
import EmptyState from "@/Components/EmptyState";
import Pill from "@/Components/Pill";
import SpecIcon from "@/Components/Characters/SpecIcon";
import SearchInput from "@/Components/Search/SearchInput";
import { Can } from "@/Components/Authorizable";
import SortableTable from "@/Components/SortableTable";
import useLocalStorage from "@/Hooks/useLocalStorage";
import raidSpec from "@/Helpers/RaidSpec";

function CharacterRowCells({ character, spec }) {
    return (
        <>
            <div
                role="cell"
                className={`border-b-brown-800 table-cell border-b px-4 align-middle py-3${character.is_known ? " border-l-2 border-l-amber-600/60" : ""}`}
            >
                <span
                    className={`inline-flex items-center gap-2 font-medium ${character.is_known ? "text-white" : "text-gray-400"}`}
                >
                    {character.name}
                    {character.is_main && (
                        <Pill bgColor="bg-amber-700" textColor="text-amber-200">
                            Main
                        </Pill>
                    )}
                </span>
            </div>
            <div role="cell" className="border-b-brown-800 table-cell border-b px-4 py-3 align-middle text-gray-300">
                {character.level}
            </div>
            <div role="cell" className="border-b-brown-800 table-cell border-b px-4 py-3 align-middle text-gray-300">
                {character.playable_race?.name ?? "—"}
            </div>
            <div role="cell" className="border-b-brown-800 table-cell border-b px-4 py-3 align-middle">
                <div className="flex flex-row items-center gap-2">
                    <SpecIcon specialization={spec} playableClass={character.playable_class} />
                    <span className="text-gray-300">
                        {character.playable_class
                            ? `${spec?.name ? `${spec.name} ` : ""}${character.playable_class.name}`
                            : "—"}
                    </span>
                </div>
            </div>
            <div role="cell" className="border-b-brown-800 table-cell border-b px-4 py-3 align-middle text-gray-300">
                {character.rank ?? "—"}
            </div>
        </>
    );
}

function CharacterRow({ character }) {
    const spec = raidSpec(character);

    if (character.is_known) {
        return (
            <Link
                role="row"
                href={route("characters.show", { character: character.id, slug: character.slug })}
                className="border-b-brown-700/50 hover:bg-brown-800/50 table-row border-b transition-colors"
            >
                <CharacterRowCells character={character} spec={spec} />
            </Link>
        );
    }

    return (
        <div role="row" className="border-brown-700/50 table-row border-b">
            <CharacterRowCells character={character} spec={spec} />
        </div>
    );
}

function CharacterCard({ character }) {
    const spec = raidSpec(character);
    const cardContent = (
        <>
            <div className="mb-3 flex items-center gap-3">
                <SpecIcon specialization={spec} playableClass={character.playable_class} size={10} />
                <div className="min-w-0 flex-1">
                    <div className="flex flex-wrap items-center gap-1.5">
                        <h3 className={`font-bold ${character.is_known ? "text-white" : "text-gray-400"}`}>
                            {character.name}
                        </h3>
                        {character.is_main && (
                            <Pill bgColor="bg-red-900/40" textColor="text-red-300">
                                Main
                            </Pill>
                        )}
                    </div>
                    <p className="text-sm text-gray-400">
                        Level {character.level} {character.playable_race?.name}{" "}
                        {character.playable_class
                            ? `${spec?.name ? `${spec.name} ` : ""}${character.playable_class.name}`
                            : null}
                    </p>
                </div>
            </div>
            <div className="flex items-center text-sm">
                <span className="text-amber-500">{character.rank ?? "—"}</span>
            </div>
        </>
    );

    if (character.is_known) {
        return (
            <Link
                href={route("characters.show", {
                    character: character.id,
                    slug: character.slug,
                })}
                className="border-brown-700 bg-brown-800/50 block rounded-lg border-y border-r border-l-2 border-amber-600/60 p-4 transition-colors hover:border-amber-600/40"
            >
                {cardContent}
            </Link>
        );
    }

    return <div className="border-brown-700 bg-brown-800/50 block rounded-lg border p-4">{cardContent}</div>;
}

function IndexSkeleton() {
    return (
        <div className="animate-pulse">
            <div className="mb-8 space-y-6">
                <div className="bg-brown-800 h-10 rounded"></div>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    {[...Array(4)].map((_, i) => (
                        <div key={i} className="bg-brown-800 h-10 rounded"></div>
                    ))}
                </div>
                <div className="bg-brown-800 h-5 w-48 rounded"></div>
            </div>
            <div className="hidden md:block">
                <div className="bg-brown-800/50 mb-2 h-12 rounded"></div>
                {[...Array(10)].map((_, i) => (
                    <div key={i} className="bg-brown-800/30 mb-1 h-14 rounded"></div>
                ))}
            </div>
            <div className="space-y-4 md:hidden">
                {[...Array(6)].map((_, i) => (
                    <div key={i} className="bg-brown-800/50 h-24 rounded-lg"></div>
                ))}
            </div>
        </div>
    );
}

export default function Index({ characters, classes, ranks, races }) {
    const isLoading = characters === undefined;

    // Filters persist client-side in localStorage so the user's last selection
    // is restored when they leave and return to the page. A null stored value
    // means "not yet customised" and resolves to all options selected below.
    const [sortColumn, setSortColumn] = useLocalStorage("roster.sort_column", "rank");
    const [sortDirection, setSortDirection] = useLocalStorage("roster.sort_direction", "asc");
    const [searchQuery, setSearchQuery] = useLocalStorage("roster.search", "");
    const [storedClasses, setSelectedClasses] = useLocalStorage("roster.class_ids", null);
    const [storedRaces, setSelectedRaces] = useLocalStorage("roster.race_ids", null);
    const [storedRanks, setSelectedRanks] = useLocalStorage("roster.rank_names", null);
    const [showKnownOnly, setShowKnownOnly] = useLocalStorage("roster.known_only", false);
    const [showMainOnly, setShowMainOnly] = useLocalStorage("roster.main_only", false);
    const [levelMin, setLevelMin] = useLocalStorage("roster.level_min", null);
    const [levelMax, setLevelMax] = useLocalStorage("roster.level_max", null);

    const selectedClasses = useMemo(() => storedClasses ?? (classes ?? []).map((c) => c.id), [storedClasses, classes]);
    const selectedRaces = useMemo(() => storedRaces ?? (races ?? []).map((r) => r.id), [storedRaces, races]);
    const selectedRanks = useMemo(() => storedRanks ?? ranks ?? [], [storedRanks, ranks]);

    const classById = useMemo(() => new Map((classes ?? []).map((c) => [c.id, c])), [classes]);
    const raceById = useMemo(() => new Map((races ?? []).map((r) => [r.id, r])), [races]);
    const rankOrder = useMemo(() => ranks ?? [], [ranks]);

    const { levelDataMin, levelDataMax } = useMemo(() => {
        if (!Array.isArray(characters)) return { levelDataMin: 1, levelDataMax: 80 };
        const levels = characters.map(({ character }) => character.level ?? 0);
        return { levelDataMin: Math.min(...levels), levelDataMax: Math.max(...levels) };
    }, [characters]);

    const filteredAndSorted = useMemo(() => {
        if (!Array.isArray(characters)) return [];

        return characters
            .map(({ character, rank }) => ({
                ...character,
                playable_class: classById.get(character.playable_class_id) ?? null,
                playable_race: raceById.get(character.playable_race_id) ?? null,
                rank,
            }))
            .filter((c) => {
                if (searchQuery && !c.name.toLowerCase().includes(searchQuery.toLowerCase())) {
                    return false;
                }
                if (selectedClasses && !selectedClasses.includes(c.playable_class?.id)) {
                    return false;
                }
                if (selectedRaces && !selectedRaces.includes(c.playable_race?.id)) {
                    return false;
                }
                if (selectedRanks && !selectedRanks.includes(c.rank)) {
                    return false;
                }
                if (levelMin !== null && c.level < levelMin) {
                    return false;
                }
                if (levelMax !== null && c.level > levelMax) {
                    return false;
                }
                if (showKnownOnly && !c.is_known) {
                    return false;
                }
                if (showMainOnly && !c.is_main) {
                    return false;
                }
                return true;
            })
            .sort((a, b) => {
                let aVal, bVal;
                switch (sortColumn) {
                    case "name":
                        aVal = a.name.toLowerCase();
                        bVal = b.name.toLowerCase();
                        break;
                    case "level":
                        aVal = a.level ?? 0;
                        bVal = b.level ?? 0;
                        break;
                    case "race":
                        aVal = a.playable_race?.name?.toLowerCase() ?? "";
                        bVal = b.playable_race?.name?.toLowerCase() ?? "";
                        break;
                    case "class":
                        aVal = a.playable_class?.name?.toLowerCase() ?? "";
                        bVal = b.playable_class?.name?.toLowerCase() ?? "";
                        break;
                    case "rank":
                    default: {
                        const ai = rankOrder.indexOf(a.rank);
                        const bi = rankOrder.indexOf(b.rank);
                        aVal = ai === -1 ? rankOrder.length : ai;
                        bVal = bi === -1 ? rankOrder.length : bi;
                        break;
                    }
                }
                if (aVal < bVal) return sortDirection === "asc" ? -1 : 1;
                if (aVal > bVal) return sortDirection === "asc" ? 1 : -1;
                return 0;
            });
    }, [
        characters,
        classById,
        raceById,
        rankOrder,
        searchQuery,
        selectedClasses,
        selectedRaces,
        selectedRanks,
        levelMin,
        levelMax,
        showKnownOnly,
        showMainOnly,
        sortColumn,
        sortDirection,
    ]);

    return (
        <Master title="Guild Roster">
            <SharedHeader title="Guild Roster" backgroundClass="bg-stormwind" />
            <PageContainer>
                {isLoading ? (
                    <IndexSkeleton />
                ) : (
                    <>
                        <div className="mb-8 space-y-4">
                            <SearchInput value={searchQuery} onChange={setSearchQuery} />
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <FilterDropdown
                                    label={{ singular: "Class", plural: "Classes" }}
                                    options={classes ?? []}
                                    selected={selectedClasses ?? []}
                                    onChange={setSelectedClasses}
                                />
                                <FilterDropdown
                                    label={{ singular: "Race", plural: "Races" }}
                                    options={races ?? []}
                                    selected={selectedRaces ?? []}
                                    onChange={setSelectedRaces}
                                />
                                <FilterDropdown
                                    label={{ singular: "Rank", plural: "Ranks" }}
                                    options={(ranks ?? []).map((name) => ({ id: name, name }))}
                                    selected={selectedRanks ?? []}
                                    onChange={setSelectedRanks}
                                />
                                <LevelRangeFilter
                                    minLevel={levelMin}
                                    maxLevel={levelMax}
                                    onMinChange={setLevelMin}
                                    onMaxChange={setLevelMax}
                                    dataMin={levelDataMin}
                                    dataMax={levelDataMax}
                                />
                                <Can permission="update-characters">
                                    <ToggleFilter
                                        label="Show managable characters only"
                                        value={showKnownOnly}
                                        onChange={setShowKnownOnly}
                                    />
                                </Can>
                                <ToggleFilter
                                    label="Main characters only"
                                    value={showMainOnly}
                                    onChange={setShowMainOnly}
                                />
                            </div>
                        </div>
                        <p className="mb-4 text-sm text-gray-200">
                            Showing {filteredAndSorted.length} of {characters.length} characters
                        </p>

                        {filteredAndSorted.length === 0 ? (
                            <EmptyState message="No characters match your filters." />
                        ) : (
                            <>
                                <div className="hidden overflow-x-auto md:block">
                                    <SortableTable
                                        columns={["name", "level", "race", "class", "rank"]}
                                        sortColumn={sortColumn}
                                        sortDirection={sortDirection}
                                        onSort={(col, dir) => {
                                            setSortColumn(col);
                                            setSortDirection(dir);
                                        }}
                                    >
                                        {filteredAndSorted.map((character) => (
                                            <CharacterRow key={character.id} character={character} />
                                        ))}
                                    </SortableTable>
                                </div>

                                <div className="space-y-3 md:hidden">
                                    {filteredAndSorted.map((character) => (
                                        <CharacterCard key={character.id} character={character} />
                                    ))}
                                </div>
                            </>
                        )}
                    </>
                )}
            </PageContainer>
        </Master>
    );
}
