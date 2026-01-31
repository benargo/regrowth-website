import Master from '@/Layouts/Master';
import SharedHeader from '@/Components/SharedHeader';
import { useState, useMemo, useRef, useEffect } from 'react';

function SortableHeader({ column, label, currentColumn, currentDirection, onSort }) {
    const isActive = currentColumn === column;

    return (
        <th
            className="px-4 py-3 text-left text-sm font-semibold text-amber-500 cursor-pointer hover:text-amber-400 transition-colors select-none"
            onClick={() => onSort(column)}
        >
            <span className="inline-flex items-center gap-2">
                {label}
                <span className="text-xs">
                    {isActive ? (
                        currentDirection === 'asc' ? (
                            <i className="fas fa-sort-up"></i>
                        ) : (
                            <i className="fas fa-sort-down"></i>
                        )
                    ) : (
                        <i className="fas fa-sort text-gray-600"></i>
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
    return <span>{count} {label.plural}</span>;
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

        document.addEventListener('mousedown', handleClickOutside);
        return () => document.removeEventListener('mousedown', handleClickOutside);
    }, []);

    const toggleOption = (id) => {
        if (selected.includes(id)) {
            onChange(selected.filter(s => s !== id));
        } else {
            onChange([...selected, id]);
        }
    };

    const selectAll = () => onChange(options.map(o => o.id));
    const selectNone = () => onChange([]);

    const selectedCount = selected.length;
    const buttonLabel = (<ButtonLabelText label={label} count={selectedCount} total={options.length} />);

    return (
        <div className="relative" ref={dropdownRef}>
            <button
                onClick={() => setIsOpen(!isOpen)}
                className="w-full px-4 py-2 bg-brown-800 border border-amber-600 rounded text-left text-white flex items-center justify-between hover:bg-brown-700 transition-colors"
            >
                <span>{buttonLabel}</span>
                <i className={`fas fa-chevron-down text-amber-500 transition-transform ${isOpen ? 'rotate-180' : ''}`}></i>
            </button>

            {isOpen && (
                <div className="absolute z-50 mt-1 w-full bg-brown-800 border border-amber-600 rounded shadow-lg max-h-64 overflow-y-auto">
                    {/* All / None buttons */}
                    <div className="flex border-b border-brown-700">
                        <button
                            onClick={selectAll}
                            className="flex-1 px-3 py-2 text-sm text-amber-500 hover:bg-brown-700 transition-colors"
                        >
                            All
                        </button>
                        <button
                            onClick={selectNone}
                            className="flex-1 px-3 py-2 text-sm text-amber-500 hover:bg-brown-700 transition-colors border-l border-brown-700"
                        >
                            None
                        </button>
                    </div>

                    {/* Options */}
                    <div className="py-1">
                        {options.map((option) => (
                            <label
                                key={option.id}
                                className="flex items-center gap-3 px-3 py-2 hover:bg-brown-700 cursor-pointer transition-colors"
                            >
                                <input
                                    type="checkbox"
                                    checked={selected.includes(option.id)}
                                    onChange={() => toggleOption(option.id)}
                                    className="w-4 h-4 rounded border-amber-600 bg-brown-900 text-amber-600 focus:ring-amber-500 focus:ring-offset-brown-800"
                                />
                                {showIcon && option.media?.assets?.[0]?.value && (
                                    <img
                                        src={option.media.assets[0].value}
                                        alt=""
                                        className="w-5 h-5 rounded"
                                    />
                                )}
                                <span className="text-white text-sm">{option.name}</span>
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
            <i className="fas fa-search absolute left-3 top-1/2 -translate-y-1/2 text-gray-500"></i>
            <input
                type="text"
                value={value}
                onChange={(e) => onChange(e.target.value)}
                placeholder={placeholder}
                className="w-full pl-10 pr-10 py-2 bg-brown-800 border border-amber-600 rounded text-white placeholder-gray-500 focus:outline-none focus:ring-1 focus:ring-amber-500"
            />
            {value && (
                <button
                    onClick={() => onChange('')}
                    className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-500 hover:text-white"
                >
                    <i className="fas fa-times"></i>
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
        <tr className="hover:bg-brown-800/50 transition-colors">
            <td className="px-4 py-3 font-medium text-white">
                {character.name}
            </td>
            <td className="px-4 py-3 text-gray-300">
                {character.level}
            </td>
            <td className="px-4 py-3 text-gray-300">
                {playableRace?.name || 'Unknown'}
            </td>
            <td className="px-4 py-3">
                <span className="inline-flex items-center gap-2">
                    {classIcon && (
                        <img
                            src={classIcon}
                            alt=""
                            className="w-5 h-5 rounded"
                        />
                    )}
                    <span className="text-gray-300">
                        {playableClass?.name || 'Unknown'}
                    </span>
                </span>
            </td>
            <td className="px-4 py-3 text-gray-300">
                {rank?.name || `Rank ${rank?.position ?? '?'}`}
            </td>
        </tr>
    );
}

function MemberCard({ member }) {
    const { character, rank } = member;
    const playableClass = character.playable_class;
    const playableRace = character.playable_race;
    const classIcon = playableClass?.media?.assets?.[0]?.value;

    return (
        <div className="bg-brown-800/50 rounded-lg p-4 border border-brown-700">
            <div className="flex items-center gap-3 mb-3">
                {classIcon && (
                    <img
                        src={classIcon}
                        alt=""
                        className="w-10 h-10 rounded"
                    />
                )}
                <div>
                    <h3 className="font-bold text-white">{character.name}</h3>
                    <p className="text-sm text-gray-400">
                        Level {character.level} {playableRace?.name} {playableClass?.name}
                    </p>
                </div>
            </div>
            <div className="text-sm text-amber-500">
                {rank?.name || `Rank ${rank?.position ?? '?'}`}
            </div>
        </div>
    );
}

export default function Roster({ members, classes, races, ranks }) {
    // Sorting state
    const [sortColumn, setSortColumn] = useState('rank');
    const [sortDirection, setSortDirection] = useState('asc');

    // Filter state
    const [searchQuery, setSearchQuery] = useState('');
    const [selectedClasses, setSelectedClasses] = useState(() =>
        (classes?.classes || []).map(c => c.id)
    );
    const [selectedRaces, setSelectedRaces] = useState(() =>
        (races?.races || []).map(r => r.id)
    );
    const [selectedRanks, setSelectedRanks] = useState(() =>
        (ranks || []).map(r => r.id)
    );

    // Handle column sort
    const handleSort = (column) => {
        if (sortColumn === column) {
            setSortDirection(sortDirection === 'asc' ? 'desc' : 'asc');
        } else {
            setSortColumn(column);
            setSortDirection('asc');
        }
    };

    // Filter and sort members
    const filteredAndSortedMembers = useMemo(() => {
        let result = [...members];

        // Filter by search query (character name)
        if (searchQuery) {
            const query = searchQuery.toLowerCase();
            result = result.filter(m =>
                m.character.name.toLowerCase().includes(query)
            );
        }

        // Filter by selected classes
        result = result.filter(m =>
            selectedClasses.includes(m.character.playable_class?.id)
        );

        // Filter by selected races
        result = result.filter(m =>
            selectedRaces.includes(m.character.playable_race?.id)
        );

        // Filter by selected ranks
        result = result.filter(m =>
            selectedRanks.includes(m.rank?.id)
        );

        // Sort
        result.sort((a, b) => {
            let aVal, bVal;
            switch (sortColumn) {
                case 'name':
                    aVal = a.character.name.toLowerCase();
                    bVal = b.character.name.toLowerCase();
                    break;
                case 'level':
                    aVal = a.character.level;
                    bVal = b.character.level;
                    break;
                case 'race':
                    aVal = a.character.playable_race?.name?.toLowerCase() || '';
                    bVal = b.character.playable_race?.name?.toLowerCase() || '';
                    break;
                case 'class':
                    aVal = a.character.playable_class?.name?.toLowerCase() || '';
                    bVal = b.character.playable_class?.name?.toLowerCase() || '';
                    break;
                case 'rank':
                    aVal = a.rank?.position ?? 999;
                    bVal = b.rank?.position ?? 999;
                    break;
                default:
                    return 0;
            }

            if (aVal < bVal) return sortDirection === 'asc' ? -1 : 1;
            if (aVal > bVal) return sortDirection === 'asc' ? 1 : -1;
            return 0;
        });

        return result;
    }, [members, searchQuery, selectedClasses, selectedRaces, selectedRanks, sortColumn, sortDirection]);

    // Extract class and race lists from the API data
    const classList = classes?.classes || [];
    const raceList = races?.races || [];

    return (
        <Master title="Guild Roster">
            <SharedHeader title="Guild Roster" />

            <main className="container mx-auto px-4 py-8">
                {/* Filters Section */}
                <div className="mb-8 space-y-6">
                    {/* Search */}
                    <SearchInput
                        value={searchQuery}
                        onChange={setSearchQuery}
                    />

                    {/* Filter Dropdowns */}
                    <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <FilterDropdown
                            label={{singular:"Class", plural:"Classes"}}
                            options={classList}
                            selected={selectedClasses}
                            onChange={setSelectedClasses}
                            showIcon={true}
                        />
                        <FilterDropdown
                            label={{singular:"Race", plural:"Races"}}
                            options={raceList}
                            selected={selectedRaces}
                            onChange={setSelectedRaces}
                        />
                        <FilterDropdown
                            label={{singular:"Rank", plural:"Ranks"}}
                            options={ranks}
                            selected={selectedRanks}
                            onChange={setSelectedRanks}
                        />
                    </div>

                    {/* Results count */}
                    <p className="text-gray-400 text-sm">
                        Showing {filteredAndSortedMembers.length} of {members.length} members
                    </p>
                </div>

                {/* Desktop Table */}
                <div className="hidden md:block overflow-x-auto">
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
                <div className="md:hidden space-y-4">
                    {filteredAndSortedMembers.map((member) => (
                        <MemberCard key={member.character.id} member={member} />
                    ))}
                </div>

                {/* Empty state */}
                {filteredAndSortedMembers.length === 0 && (
                    <div className="text-center py-12 text-gray-400">
                        <i className="fas fa-users-slash text-4xl mb-4"></i>
                        <p>No members match your filters.</p>
                    </div>
                )}
            </main>
        </Master>
    );
}
