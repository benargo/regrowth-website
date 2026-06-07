import { useState, useMemo } from "react";
import { Link, router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import FilterDropdown from "@/Components/FilterDropdown";
import EmptyState from "@/Components/EmptyState";
import Pill from "@/Components/Pill";
import SpecIcon from "@/Components/Characters/SpecIcon";
import SearchInput from "@/Components/SearchInput";
import SortableTable from "@/Components/SortableTable";
import raidSpec from "@/Helpers/RaidSpec";

function CharacterRow({ character }) {
    const spec = raidSpec(character);

    return (
        <tr
            className={character.is_known ? "cursor-pointer transition-colors hover:bg-brown-800/50" : undefined}
            onClick={
                character.is_known
                    ? () =>
                          router.visit(
                              route("characters.show", {
                                  character: character.id,
                                  slug: character.slug,
                              }),
                          )
                    : undefined
            }
        >
            <td className="px-4 py-3">
                <span className="inline-flex items-center gap-2 font-medium text-white">
                    {character.name}
                    {character.is_main && (
                        <Pill bgColor="bg-amber-700" textColor="text-amber-200">
                            Main
                        </Pill>
                    )}
                    {character.is_loot_councillor && (
                        <Pill bgColor="bg-purple-700" textColor="text-purple-200">
                            LC
                        </Pill>
                    )}
                </span>
            </td>
            <td className="px-4 py-3 text-gray-300">{character.level}</td>
            <td className="px-4 py-3 text-gray-300">{character.playable_race?.name ?? "—"}</td>
            <td className="px-4 py-3">
                <span className="inline-flex items-center gap-2">
                    <SpecIcon specialization={spec} playableClass={character.playable_class} />
                    <span className="text-gray-300">
                        {character.playable_class
                            ? `${spec?.name ? `${spec.name} ` : ""}${character.playable_class.name}`
                            : "—"}
                    </span>
                </span>
            </td>
            <td className="px-4 py-3 text-gray-300">{character.rank?.name ?? "—"}</td>
        </tr>
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
                        <h3 className="font-bold text-white">{character.name}</h3>
                        {character.is_main && (
                            <Pill bgColor="bg-amber-700" textColor="text-amber-200">
                                Main
                            </Pill>
                        )}
                        {character.is_loot_councillor && (
                            <Pill bgColor="bg-purple-700" textColor="text-purple-200">
                                LC
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
                <span className="text-amber-500">{character.rank?.name ?? "—"}</span>
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
                className="block rounded-lg border border-brown-700 bg-brown-800/50 p-4 transition-colors hover:border-amber-600/40"
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

export default function Index({ characters, classes, ranks, races }) {
    const isLoading = characters === undefined;

    const [sortColumn, setSortColumn] = useState("rank");
    const [sortDirection, setSortDirection] = useState("asc");
    const [searchQuery, setSearchQuery] = useState("");
    const [selectedClasses, setSelectedClasses] = useState(() => classes?.map((c) => c.id));
    const [selectedRaces, setSelectedRaces] = useState(() => races?.map((r) => r.id));
    const [selectedRanks, setSelectedRanks] = useState(() => ranks?.map((r) => r.id));

    const classById = useMemo(() => new Map((classes ?? []).map((c) => [c.id, c])), [classes]);
    const raceById = useMemo(() => new Map((races ?? []).map((r) => [r.id, r])), [races]);
    const rankByPos = useMemo(() => new Map((ranks ?? []).map((r) => [r.position, r])), [ranks]);

    const filteredAndSorted = useMemo(() => {
        if (!Array.isArray(characters)) return [];

        return characters
            .map(({ character, rank }) => ({
                ...character,
                playable_class: classById.get(character.playable_class_id) ?? null,
                playable_race: raceById.get(character.playable_race_id) ?? null,
                rank: rankByPos.get(rank) ?? null,
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
                if (selectedRanks && !selectedRanks.includes(c.rank?.id)) {
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
                    default:
                        aVal = a.rank?.sort_order ?? 9999;
                        bVal = b.rank?.sort_order ?? 9999;
                        break;
                }
                if (aVal < bVal) return sortDirection === "asc" ? -1 : 1;
                if (aVal > bVal) return sortDirection === "asc" ? 1 : -1;
                return 0;
            });
    }, [characters, classById, raceById, rankByPos, searchQuery, selectedClasses, selectedRaces, selectedRanks, sortColumn, sortDirection]);

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
                                    options={ranks ?? []}
                                    selected={selectedRanks ?? []}
                                    onChange={setSelectedRanks}
                                />
                            </div>
                            <p className="text-sm text-gray-500">
                                {filteredAndSorted.length} of {characters.length} characters
                            </p>
                        </div>

                        {filteredAndSorted.length === 0 ? (
                            <EmptyState message="No characters match your filters." />
                        ) : (
                            <>
                                <div className="hidden overflow-x-auto md:block">
                                    <SortableTable
                                        columns={["name", "level", "race", "class", "rank"]}
                                        defaultSortColumn="rank"
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
