import { useState, useMemo } from "react";
import { Link, router } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import FilterDropdown from "@/Components/FilterDropdown";
import EmptyState from "@/Components/EmptyState";
import Icon from "@/Components/FontAwesome/Icon";
import Pill from "@/Components/Pill";
import SpecIcon from "@/Components/Characters/SpecIcon";

function SearchInput({ value, onChange }) {
    return (
        <div className="relative">
            <Icon icon="search" style="solid" className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
            <input
                type="text"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder="Search by name..."
                className="w-full rounded border border-amber-600 bg-brown-800 py-2 pl-10 pr-10 text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            />
            <button
                onClick={() => onChange("")}
                className={`absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white ${value ? "" : "invisible"}`}
            >
                <Icon icon="times" style="solid" />
            </button>
        </div>
    );
}

function SortableHeader({ column, label, currentColumn, currentDirection, onSort }) {
    const isActive = currentColumn === column;

    return (
        <th
            className="cursor-pointer select-none px-4 py-3 text-left text-sm font-semibold text-amber-500 transition-colors hover:text-amber-400"
            onClick={() => onSort(column)}
        >
            <span className="inline-flex items-center gap-2">
                {label}
                <span className="text-xs">
                    <Icon
                        icon="sort-up"
                        style="solid"
                        className={isActive && currentDirection === "asc" ? "" : "hidden"}
                    />
                    <Icon
                        icon="sort-down"
                        style="solid"
                        className={isActive && currentDirection === "desc" ? "" : "hidden"}
                    />
                    <Icon icon="sort" style="solid" className={`text-gray-600 ${!isActive ? "" : "hidden"}`} />
                </span>
            </span>
        </th>
    );
}

function raidSpec(character) {
    return Array.isArray(character.specializations)
        ? (character.specializations.find((s) => s.is_raid_spec) ?? null)
        : null;
}

function CharacterRow({ character }) {
    const spec = raidSpec(character);

    return (
        <tr
            className="cursor-pointer transition-colors hover:bg-brown-800/50"
            onClick={() =>
                router.visit(
                    route("management.characters.show", {
                        character: character.id,
                        slug: character.slug,
                    }),
                )
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

    return (
        <Link
            href={route("management.characters.show", {
                character: character.id,
                slug: character.slug,
            })}
            className="block rounded-lg border border-brown-700 bg-brown-800/50 p-4 transition-colors hover:border-amber-600/40"
        >
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
        </Link>
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

    const handleSort = (column) => {
        if (sortColumn === column) {
            setSortDirection(sortDirection === "asc" ? "desc" : "asc");
        } else {
            setSortColumn(column);
            setSortDirection("asc");
        }
    };

    const filteredAndSorted = useMemo(() => {
        if (!Array.isArray(characters)) return [];

        return characters
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
    }, [characters, searchQuery, selectedClasses, selectedRaces, selectedRanks, sortColumn, sortDirection]);

    return (
        <div>
            <SharedHeader title="Characters" />
            <PageContainer>
                {isLoading ? (
                    <IndexSkeleton />
                ) : (
                    <>
                        <div className="mb-8 space-y-4">
                            <SearchInput value={searchQuery} onChange={setSearchQuery} />
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <FilterDropdown
                                    label="Class"
                                    options={classes ?? []}
                                    selected={selectedClasses ?? []}
                                    onChange={setSelectedClasses}
                                />
                                <FilterDropdown
                                    label="Race"
                                    options={races ?? []}
                                    selected={selectedRaces ?? []}
                                    onChange={setSelectedRaces}
                                />
                                <FilterDropdown
                                    label="Rank"
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
                                    <table className="w-full text-left">
                                        <thead>
                                            <tr className="border-b border-brown-700">
                                                <SortableHeader
                                                    column="name"
                                                    label="Name"
                                                    currentColumn={sortColumn}
                                                    currentDirection={sortDirection}
                                                    onSort={handleSort}
                                                />
                                                <SortableHeader
                                                    column="level"
                                                    label="Level"
                                                    currentColumn={sortColumn}
                                                    currentDirection={sortDirection}
                                                    onSort={handleSort}
                                                />
                                                <SortableHeader
                                                    column="race"
                                                    label="Race"
                                                    currentColumn={sortColumn}
                                                    currentDirection={sortDirection}
                                                    onSort={handleSort}
                                                />
                                                <SortableHeader
                                                    column="class"
                                                    label="Class"
                                                    currentColumn={sortColumn}
                                                    currentDirection={sortDirection}
                                                    onSort={handleSort}
                                                />
                                                <SortableHeader
                                                    column="rank"
                                                    label="Rank"
                                                    currentColumn={sortColumn}
                                                    currentDirection={sortDirection}
                                                    onSort={handleSort}
                                                />
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-brown-700/50">
                                            {filteredAndSorted.map((character) => (
                                                <CharacterRow key={character.id} character={character} />
                                            ))}
                                        </tbody>
                                    </table>
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
        </div>
    );
}

Index.layout = (page) => <Master>{page}</Master>;
