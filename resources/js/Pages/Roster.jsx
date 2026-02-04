import { useState, useMemo, useRef, useEffect } from "react";
import Master from "@/Layouts/Master";
import Icon from "@/Components/FontAwesome/Icon";
import SharedHeader from "@/Components/SharedHeader";

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
                    {isActive ? (
                        currentDirection === "asc" ? (
                            <Icon icon="sort-up" style="solid" />
                        ) : (
                            <Icon icon="sort-down" style="solid" />
                        )
                    ) : (
                        <Icon icon="sort" style="solid" className="text-gray-600" />
                    )}
                </span>
            </span>
        </th>
    );
}

function ButtonLabelText({ label, count, total }) {
    if (count === 0 || count === total) {
        return <span>All {label.plural}</span>;
    }
    if (count === 1) {
        return <span>1 {label.singular}</span>;
    }
    return (
        <span>
            {count} {label.plural}
        </span>
    );
}

function FilterDropdown({ label, options, selected, onChange, showIcon = false }) {
    const [isOpen, setIsOpen] = useState(false);
    const dropdownRef = useRef(null);

    useEffect(() => {
        const handleClickOutside = (event) => {
            if (dropdownRef.current && !dropdownRef.current.contains(event.target)) {
                setIsOpen(false);
            }
        };

        document.addEventListener("mousedown", handleClickOutside);
        return () => document.removeEventListener("mousedown", handleClickOutside);
    }, []);

    const toggleOption = (id) => {
        if (selected.includes(id)) {
            onChange(selected.filter((s) => s !== id));
        } else {
            onChange([...selected, id]);
        }
    };

    const selectAll = () => onChange(options.map((o) => o.id));
    const selectNone = () => onChange([]);

    const selectedCount = selected.length;
    const buttonLabel = <ButtonLabelText label={label} count={selectedCount} total={options.length} />;

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="flex w-full items-center justify-between rounded border border-amber-600 bg-brown-800 px-4 py-2 text-left text-white transition-colors hover:bg-brown-700"
            >
                <span>{buttonLabel}</span>
                <Icon
                    icon="chevron-down"
                    className={`text-amber-500 transition-transform ${isOpen ? "rotate-180" : ""}`}
                />
            </button>

            {isOpen && (
                <div className="absolute z-50 mt-1 max-h-64 w-full overflow-y-auto rounded border border-amber-600 bg-brown-800 shadow-lg">
                    {/* All / None buttons */}
                    <div className="flex border-b border-brown-700">
                        <button
                            onClick={selectAll}
                            className="flex-1 px-3 py-2 text-sm text-amber-500 transition-colors hover:bg-brown-700"
                        >
                            All
                        </button>
                        <button
                            onClick={selectNone}
                            className="flex-1 border-l border-brown-700 px-3 py-2 text-sm text-amber-500 transition-colors hover:bg-brown-700"
                        >
                            None
                        </button>
                    </div>

                    {/* Options */}
                    <div className="py-1">
                        {options.map((option) => (
                            <label
                                key={option.id}
                                className="flex cursor-pointer items-center gap-3 px-3 py-2 transition-colors hover:bg-brown-700"
                            >
                                <input
                                    type="checkbox"
                                    checked={selected.includes(option.id)}
                                    onChange={() => toggleOption(option.id)}
                                    className="h-4 w-4 rounded border-amber-600 bg-brown-900 text-amber-600 focus:ring-amber-500 focus:ring-offset-brown-800"
                                />
                                {showIcon && option.media?.assets?.[0]?.value && (
                                    <img src={option.media.assets[0].value} alt="" className="h-5 w-5 rounded" />
                                )}
                                <span className="text-sm text-white">{option.name}</span>
                            </label>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
}

function SearchInput({ value, onChange, placeholder = "Search by name..." }) {
    return (
        <div className="relative">
            <Icon icon="search" style="solid" className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-500" />
            <input
                type="text"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                className="w-full rounded border border-amber-600 bg-brown-800 py-2 pl-10 pr-10 text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            />
            {value && (
                <button
                    onClick={() => onChange("")}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white"
                >
                    <Icon icon="times" style="solid" />
                </button>
            )}
        </div>
    );
}

function RosterRow({ member }) {
    const { character, rank } = member;
    const playableClass = character.playable_class;
    const playableRace = character.playable_race;
    const classIcon = playableClass?.media?.assets?.[0]?.value;

    return (
        <tr className="transition-colors hover:bg-brown-800/50">
            <td className="px-4 py-3 font-medium text-white">{character.name}</td>
            <td className="px-4 py-3 text-gray-300">{character.level}</td>
            <td className="px-4 py-3 text-gray-300">{playableRace?.name || "Unknown"}</td>
            <td className="px-4 py-3">
                <span className="inline-flex items-center gap-2">
                    {classIcon && <img src={classIcon} alt="" className="h-5 w-5 rounded" />}
                    <span className="text-gray-300">{playableClass?.name || "Unknown"}</span>
                </span>
            </td>
            <td className="px-4 py-3 text-gray-300">{rank?.name || `Rank ${rank?.position ?? "?"}`}</td>
        </tr>
    );
}

function MemberCard({ member }) {
    const { character, rank } = member;
    const playableClass = character.playable_class;
    const playableRace = character.playable_race;
    const classIcon = playableClass?.media?.assets?.[0]?.value;

    return (
        <div className="rounded-lg border border-brown-700 bg-brown-800/50 p-4">
            <div className="mb-3 flex items-center gap-3">
                {classIcon && <img src={classIcon} alt="" className="h-10 w-10 rounded" />}
                <div>
                    <h3 className="font-bold text-white">{character.name}</h3>
                    <p className="text-sm text-gray-400">
                        Level {character.level} {playableRace?.name} {playableClass?.name}
                    </p>
                </div>
            </div>
            <div className="text-sm text-amber-500">{rank?.name || `Rank ${rank?.position ?? "?"}`}</div>
        </div>
    );
}

function RosterSkeleton() {
    return (
        <div className="animate-pulse">
            {/* Search skeleton */}
            <div className="mb-8 space-y-6">
                <div className="h-10 rounded bg-brown-800"></div>
                <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div className="h-10 rounded bg-brown-800"></div>
                    <div className="h-10 rounded bg-brown-800"></div>
                    <div className="h-10 rounded bg-brown-800"></div>
                </div>
                <div className="h-5 w-48 rounded bg-brown-800"></div>
            </div>

            {/* Table skeleton - desktop */}
            <div className="hidden md:block">
                <div className="mb-2 h-12 rounded bg-brown-800/50"></div>
                {[...Array(10)].map((_, i) => (
                    <div key={i} className="mb-1 h-14 rounded bg-brown-800/30"></div>
                ))}
            </div>

            {/* Card skeleton - mobile */}
            <div className="space-y-4 md:hidden">
                {[...Array(6)].map((_, i) => (
                    <div key={i} className="h-24 rounded-lg bg-brown-800/50"></div>
                ))}
            </div>
        </div>
    );
}

export default function Roster({ members, classes, races, ranks }) {
    const isLoading = members === undefined;

    // Sorting state
    const [sortColumn, setSortColumn] = useState("rank");
    const [sortDirection, setSortDirection] = useState("asc");

    // Filter state
    const [searchQuery, setSearchQuery] = useState("");
    const [selectedClasses, setSelectedClasses] = useState(() => classes?.map((c) => c.id));
    const [selectedRaces, setSelectedRaces] = useState(() => races?.map((r) => r.id));
    const [selectedRanks, setSelectedRanks] = useState(() => ranks?.map((r) => r.id));

    // Handle column sort
    const handleSort = (column) => {
        if (sortColumn === column) {
            setSortDirection(sortDirection === "asc" ? "desc" : "asc");
        } else {
            setSortColumn(column);
            setSortDirection("asc");
        }
    };

    // Filter and sort members
    const filteredAndSortedMembers = useMemo(() => {
        if (!members) return [];
        let result = [...members];

        // Filter by search query (character name)
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            result = result.filter((m) => m.character.name.toLowerCase().includes(query));
        }

        // Filter by selected classes
        result = result.filter((m) => selectedClasses.includes(m.character.playable_class?.id));

        // Filter by selected races
        result = result.filter((m) => selectedRaces.includes(m.character.playable_race?.id));

        // Filter by selected ranks
        result = result.filter((m) => selectedRanks.includes(m.rank?.id));

        // Sort
        result.sort((a, b) => {
            let aVal, bVal;
            switch (sortColumn) {
                case "name":
                    aVal = a.character.name.toLowerCase();
                    bVal = b.character.name.toLowerCase();
                    break;
                case "level":
                    aVal = a.character.level;
                    bVal = b.character.level;
                    break;
                case "race":
                    aVal = a.character.playable_race?.name?.toLowerCase() || "";
                    bVal = b.character.playable_race?.name?.toLowerCase() || "";
                    break;
                case "class":
                    aVal = a.character.playable_class?.name?.toLowerCase() || "";
                    bVal = b.character.playable_class?.name?.toLowerCase() || "";
                    break;
                case "rank":
                    aVal = a.rank?.position ?? 999;
                    bVal = b.rank?.position ?? 999;
                    break;
                default:
                    return 0;
            }

            if (aVal < bVal) return sortDirection === "asc" ? -1 : 1;
            if (aVal > bVal) return sortDirection === "asc" ? 1 : -1;
            return 0;
        });

        return result;
    }, [members, searchQuery, selectedClasses, selectedRaces, selectedRanks, sortColumn, sortDirection]);

    return (
        <Master title="Guild Roster">
            <SharedHeader backgroundClass="bg-goldshire" title="Guild Roster" />

            <main className="container mx-auto px-4 py-8">
                {isLoading ? (
                    <RosterSkeleton />
                ) : (
                    <>
                        {/* Filters Section */}
                        <div className="mb-8 space-y-6">
                            {/* Search */}
                            <SearchInput value={searchQuery} onChange={setSearchQuery} />

                            {/* Filter Dropdowns */}
                            <div className="grid grid-cols-1 gap-4 md:grid-cols-3">
                                <FilterDropdown
                                    label={{ singular: "Class", plural: "Classes" }}
                                    options={classes}
                                    selected={selectedClasses}
                                    onChange={setSelectedClasses}
                                    showIcon={true}
                                />
                                <FilterDropdown
                                    label={{ singular: "Race", plural: "Races" }}
                                    options={races}
                                    selected={selectedRaces}
                                    onChange={setSelectedRaces}
                                />
                                <FilterDropdown
                                    label={{ singular: "Rank", plural: "Ranks" }}
                                    options={ranks}
                                    selected={selectedRanks}
                                    onChange={setSelectedRanks}
                                />
                            </div>

                            {/* Results count */}
                            <p className="text-sm text-gray-400">
                                Showing {filteredAndSortedMembers.length} of {members.length} members
                            </p>
                        </div>

                        {/* Desktop Table */}
                        <div className="hidden overflow-x-auto md:block">
                            <table className="w-full">
                                <thead className="border-b border-amber-600">
                                    <tr>
                                        <SortableHeader
                                            column="name"
                                            label="Character"
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
                                <tbody className="divide-y divide-brown-700">
                                    {filteredAndSortedMembers.map((member) => (
                                        <RosterRow key={member.character.id} member={member} />
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        {/* Mobile Cards */}
                        <div className="space-y-4 md:hidden">
                            {filteredAndSortedMembers.map((member) => (
                                <MemberCard key={member.character.id} member={member} />
                            ))}
                        </div>

                        {/* Empty state */}
                        {filteredAndSortedMembers.length === 0 && (
                            <div className="py-12 text-center text-gray-400">
                                <Icon icon="users-slash" style="solid" className="mb-4 text-4xl" />
                                <p>No members match your filters.</p>
                            </div>
                        )}
                    </>
                )}
            </main>
        </Master>
    );
}
