import Master from '@/Layouts/Master';
import { useState } from 'react';
import { router, Deferred, Link } from '@inertiajs/react';

function ItemsSkeleton() {
    return (
        <div className="space-y-2 animate-pulse">
            {[1, 2, 3].map((i) => (
                <div key={i} className="h-12 bg-amber-600/20 rounded" />
            ))}
        </div>
    );
}

function PriorityItem({ priority }) {
    return (
        <span className="inline-flex items-center gap-1">
            {priority.media && (
                <img src={priority.media} alt="" className="w-4 h-4" />
            )}
            <span>{priority.title}</span>
        </span>
    );
}

function PriorityDisplay({ priorities }) {
    if (!priorities || priorities.length === 0) {
        return <p className="text-gray-500 italic">Item not subject to loot council.</p>;
    }

    // Sort by weight (ascending) and group by weight
    const sorted = [...priorities].sort((a, b) => a.weight - b.weight);
    const grouped = sorted.reduce((acc, priority) => {
        const weight = priority.weight;
        if (!acc[weight]) {
            acc[weight] = [];
        }
        acc[weight].push(priority);
        return acc;
    }, {});

    // Build display: join same-weight with " = ", different weights with " > "
    const weights = Object.keys(grouped).sort((a, b) => a - b);

    return (
        <span className="text-md inline-flex items-center flex-wrap gap-1">
            {weights.map((weight, weightIndex) => (
                <span key={weight} className="inline-flex items-center gap-1">
                    {weightIndex > 0 && <span className="font-bold text-xl text-amber-600 mx-1">&gt;</span>}
                    {grouped[weight].map((priority, index) => (
                        <span key={priority.id} className="inline-flex items-center gap-1">
                            {index > 0 && <span className="font-bold text-xl text-amber-600 mx-1">=</span>}
                            <PriorityItem priority={priority} />
                        </span>
                    ))}
                </span>
            ))}
        </span>
    );
}

function ItemRow({ item }) {
    return (
        <Link href={route('loot.items.show', { item: item.data.id })} className="flex flex-wrap items-center gap-4 p-2 bg-brown-800/50 rounded hover:bg-brown-800/70 transition-colors">
            {item.data.icon && (
                <a href={route('loot.items.show', { item: item.data.id })} data-wowhead={`item=${item.data.id}&domain=tbc`} target="_blank" rel="noopener noreferrer">
                    <img
                        src={item.data.icon}
                        alt={item.data.name}
                        className="w-8 h-8 rounded"
                        data-wowhead={`item=${item.data.id}&domain=tbc`}
                    />
                </a>
            )}
            <div className="text-left">
                <h4 className="text-md font-bold">{item.data.name}</h4>
                <p className="text-xs">Item ID: {item.data.id}</p>
            </div>
            <div className="ml-auto text-right">
                <PriorityDisplay priorities={item.data.priorities} />
            </div>
        </Link>
    );
}

function BossItems({ items, grouped=true }) {
    if (!items || items.length === 0) {
        return <p className="text-gray-500 italic">No items configured for this boss.</p>;
    }

    if (!grouped) {
        return (
            <div className="space-y-2">
                {items.map((item) => (
                    <ItemRow key={item.data.id} item={item} />
                ))}
            </div>
        );
    }

    // Separate grouped and ungrouped items
    const groupedItems = items.filter((item) => item.data.group);
    const ungroupedItems = items
        .filter((item) => !item.data.group)
        .sort((a, b) => a.data.name.localeCompare(b.data.name));

    // Group items by their group name and sort within each group
    const groups = groupedItems.reduce((acc, item) => {
        const groupName = item.data.group;
        if (!acc[groupName]) {
            acc[groupName] = [];
        }
        acc[groupName].push(item);
        return acc;
    }, {});

    // Sort items within each group by name
    Object.keys(groups).forEach((groupName) => {
        groups[groupName].sort((a, b) => a.data.name.localeCompare(b.data.name));
    });

    const groupNames = Object.keys(groups);

    return (
        <div className="space-y-4">
            {groupNames.map((groupName) => (
                <div key={groupName} className="space-y-2 mb-8">
                    <h4 className="text-sm font-semibold text-amber-500">{groupName}</h4>
                    {groups[groupName].map((item) => (
                        <ItemRow key={item.data.id} item={item} />
                    ))}
                </div>
            ))}
            {ungroupedItems.length > 0 && (
                <div className="space-y-2">
                    {ungroupedItems.map((item) => (
                        <ItemRow key={item.data.id} item={item} />
                    ))}
                </div>
            )}
        </div>
    );
}

export default function Index({ phases, current_phase, raids, bosses, items, selected_raid_id }) {
    const [selectedPhase, setSelectedPhase] = useState(current_phase);
    const [selectedRaid, setSelectedRaid] = useState(selected_raid_id);
    const [expandedBosses, setExpandedBosses] = useState({});

    const handlePhaseChange = (phaseId) => {
        setSelectedPhase(phaseId);
        const firstRaidInPhase = raids[phaseId]?.[0]?.id ?? null;
        setSelectedRaid(firstRaidInPhase);

        if (firstRaidInPhase) {
            router.visit(route('loot.index', { raid_id: firstRaidInPhase }), {
                only: ['items', 'selected_raid_id'],
                preserveState: true,
                preserveScroll: true,
            });
        }
    };

    const handleRaidChange = (raidId) => {
        setSelectedRaid(raidId);

        router.visit(route('loot.index', { raid_id: raidId }), {
            only: ['items', 'selected_raid_id'],
            preserveState: true,
            preserveScroll: true,
        });
    };

    const toggleBoss = (bossId) => {
        setExpandedBosses((prev) => ({
            ...prev,
            [bossId]: !prev[bossId],
        }));
    };

    const currentRaids = raids[selectedPhase] ?? [];
    const currentBosses = bosses[selectedRaid] ?? [];

    const getItemsForBoss = (bossId) => {
        return items?.[bossId] ?? items?.[bossId] ?? [];
    };

    return (
        <Master title="Loot Bias">
            {/* Header */}
            <div className="bg-karazhan py-24 text-white">
                <div className="container mx-auto px-4">
                    <h1 className="text-4xl font-bold text-center">
                        Loot Bias
                    </h1>
                    {/* Insert search bar here in the future */}
                </div>
            </div>
            {/* Content */}
            <main className="container mx-auto px-4 py-8">
                <div className="flex flex-wrap gap-2 mb-8 animate-in fade-in duration-300">
                    {phases.map((phase) => (
                        <button
                            key={phase.id}
                            onClick={() => handlePhaseChange(phase.id)}
                            className={`px-4 py-2 rounded border transition-colors ${
                                selectedPhase === phase.id
                                    ? 'bg-amber-600 border-amber-600 text-white'
                                    : 'border-amber-600 text-amber-600 hover:bg-amber-600/20'
                            }`}
                            title={`View loot for phase ${phase.id}`}
                        >
                            Phase {phase.id}
                        </button>
                    ))}
                </div>
                <div className="flex flex-wrap gap-2 mb-8">
                    {currentRaids.map((raid) => (
                        <button
                            key={raid.id}
                            onClick={() => handleRaidChange(raid.id)}
                            className={`px-4 py-2 rounded border transition-colors ${
                                selectedRaid === raid.id
                                    ? 'bg-amber-600 border-amber-600 text-white'
                                    : 'border-amber-600 text-amber-600 hover:bg-amber-600/20'
                            }`}
                        >
                            {raid.name}
                        </button>
                    ))}
                </div>
                <div className="flex flex-col gap-2">
                    {currentBosses.map((boss) => (
                        <div key={boss.id} className="border border-amber-600 rounded-md">
                            <button
                                onClick={() => toggleBoss(boss.id)}
                                className="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-amber-600/10 transition-colors"
                            >
                                <i
                                    className={`fas fa-chevron-down transition-transform duration-500 ${
                                        expandedBosses[boss.id] ? '-rotate-180' : ''
                                    }`}
                                />
                                <h3 className="text-lg font-semibold">{boss.name}</h3>
                            </button>
                            {expandedBosses[boss.id] && (
                                <div className="px-4 py-3 border-t border-amber-600">
                                    <Deferred data="items" fallback={<ItemsSkeleton />}>
                                        <BossItems items={getItemsForBoss(boss.id)} />
                                    </Deferred>
                                </div>
                            )}
                        </div>
                    ))}
                    {getItemsForBoss(-1).length > 0 && (
                        <div key="-1" className="border border-amber-600 rounded">
                            <button
                                onClick={() => toggleBoss(-1)}
                                className="w-full flex items-center gap-3 px-4 py-3 text-left hover:bg-amber-600/10 transition-colors"
                            >
                                <i
                                    className={`fas fa-chevron-down transition-transform duration-500 ${
                                        expandedBosses[-1] ? '-rotate-180' : ''
                                    }`}
                                />
                                <h3 className="text-lg font-semibold">Trash drops</h3>
                            </button>
                            {expandedBosses[-1] && (
                                <div className="px-4 py-3 border-t border-amber-600">
                                    <Deferred data="items" fallback={<ItemsSkeleton />}>
                                        <BossItems items={getItemsForBoss(-1)} grouped={false} />
                                    </Deferred>
                                </div>
                            )}
                        </div>
                    )}
                </div>
            </main>
        </Master>
    );
}
