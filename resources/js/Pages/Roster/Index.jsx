import { useMemo, useState } from "react";
import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import FilterDropdown from "@/Components/FilterDropdown";
import ToggleFilter from "@/Components/ToggleFilter";
import EmptyState from "@/Components/EmptyState";
import Pill from "@/Components/Pill";
import SpecIcon from "@/Components/Characters/SpecIcon";
import SearchInput from "@/Components/SearchInput";
import { Can } from "@/Components/Authorizable";
import SortableTable from "@/Components/SortableTable";
import { decodeFilter } from "@/Helpers/EncodeFilter";
import raidSpec from "@/Helpers/RaidSpec";

function CharacterRowCells({ character, spec }) {
    return (
        <>
            <div role="cell" className={`table-cell align-middle px-4 py-3${character.is_known ? " border-l-2 border-l-amber-600/60" : ""}`}>
                <span className={`inline-flex items-center gap-2 font-medium ${character.is_known ? "text-white" : "text-gray-400"}`}>
                    {character.name}
                    {character.is_main && (
                        <Pill bgColor="bg-amber-700" textColor="text-amber-200">
                            Main
                        </Pill>
                    )}
                </span>
            </div>
            <div role="cell" className="table-cell align-middle px-4 py-3 text-gray-300">{character.level}</div>
            <div role="cell" className="table-cell align-middle px-4 py-3 text-gray-300">{character.playable_race?.name ?? "—"}</div>
            <div role="cell" className="table-cell align-middle px-4 py-3">
                <span className="inline-flex items-center gap-2">
                    <SpecIcon specialization={spec} playableClass={character.playable_class} />
                    <span className="text-gray-300">
                        {character.playable_class
                            ? `${spec?.name ? `${spec.name} ` : ""}${character.playable_class.name}`
                            : "—"}
                    </span>
                </span>
            </div>
            <div role="cell" className="table-cell align-middle px-4 py-3 text-gray-300">{character.rank ?? "—"}</div>
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
                className="table-row border-b border-b-brown-700/50 transition-colors hover:bg-brown-800/50"
            >
                <CharacterRowCells character={character} spec={spec} />
            </Link>
        );
    }

    return (
        <div role="row" className="table-row border-b border-brown-700/50">
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
                className="block rounded-lg border-l-2 border-amber-600/60 border-y border-r border-brown-700 bg-brown-800/50 p-4 transition-colors hover:border-amber-600/40"
            >
                {cardContent}
            </Link>
        );
    }

    return (
        <div className="block rounded-lg border border-brown-700 bg-brown-800/50 p-4">
            {cardContent}
        </div>
    );
}

function IndexSkeleton() {
    return (
        <div className="animate-pulse">
            <div className="mb-8 space-y-6">
                <div className="h-10 rounded bg-brown-800"></div>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-4">
                    {[...Array(4)].map((_, i) => (
                        <div key={i} className="h-10 rounded bg-brown-800"></div>
                    ))}
                </div>
                <div className="h-5 w-48 rounded bg-brown-800"></div>
            </div>
            <div className="hidden md:block">
                <div className="mb-2 h-12 rounded bg-brown-800/50"></div>
                {[...Array(10)].map((_, i) => (
                    <div key={i} className="mb-1 h-14 rounded bg-brown-800/30"></div>
                ))}
            </div>
            <div className="space-y-4 md:hidden">
                {[...Array(6)].map((_, i) => (
                    <div key={i} className="h-24 rounded-lg bg-brown-800/50"></div>
                ))}
            </div>
        </div>
    );
}

export default function Index({ characters, classes, ranks, races, filters }) {
    const isLoading = characters === undefined;

    const [sortColumn, setSortColumn] = useState(() => filters?.sort_column ?? "rank");
    const [sortDirection, setSortDirection] = useState(() => filters?.sort_direction ?? "asc");
    const [searchQuery, setSearchQuery] = useState(() => filters?.search ?? "");
    const [selectedClasses, setSelectedClasses] = useState(() =>
        decodeFilter(filters?.class_ids, classes?.map((c) => c.id) ?? []),
    );
    const [selectedRaces, setSelectedRaces] = useState(() =>
        decodeFilter(filters?.race_ids, races?.map((r) => r.id) ?? []),
    );
    const [selectedRanks, setSelectedRanks] = useState(() => {
        const value = filters?.rank_names;
        if (value === null || value === undefined) return ranks ?? [];
        if (Array.isArray(value)) return value;
        return value.split(",");
    });
    const [showKnownOnly, setShowKnownOnly] = useState(() => filters?.known_only === "1");
    const [showMainOnly, setShowMainOnly] = useState(() => filters?.main_only === "1");

    const classById = useMemo(() => new Map((classes ?? []).map((c) => [c.id, c])), [classes]);
    const raceById = useMemo(() => new Map((races ?? []).map((r) => [r.id, r])), [races]);
    const rankOrder = useMemo(() => ranks ?? [], [ranks]);

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
    }, [characters, classById, raceById, rankOrder, searchQuery, selectedClasses, selectedRaces, selectedRanks, showKnownOnly, showMainOnly, sortColumn, sortDirection]);

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
                                    label={{singular: "Class", plural: "Classes"}}
                                    options={classes ?? []}
                                    selected={selectedClasses ?? []}
                                    onChange={setSelectedClasses}
                                />
                                <FilterDropdown
                                    label={{singular: "Race", plural: "Races"}}
                                    options={races ?? []}
                                    selected={selectedRaces ?? []}
                                    onChange={setSelectedRaces}
                                />
                                <FilterDropdown
                                    label={{singular: "Rank", plural: "Ranks"}}
                                    options={(ranks ?? []).map((name) => ({ id: name, name }))}
                                    selected={selectedRanks ?? []}
                                    onChange={setSelectedRanks}
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
