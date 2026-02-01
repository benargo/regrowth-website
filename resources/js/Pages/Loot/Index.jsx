import Master from '@/Layouts/Master';
import { useState } from 'react';
import { router, Link } from '@inertiajs/react';
import LootPageHeader from '@/Components/Loot/LootPageHeader';
import BossCollapse from '@/Components/Loot/BossCollapse';

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

export default function Index({ phases, current_phase, raids, bosses, selected_raid_id, boss_items }) {
    const [selectedPhase, setSelectedPhase] = useState(current_phase);
    const [selectedRaid, setSelectedRaid] = useState(selected_raid_id);
    const [loadedItems, setLoadedItems] = useState({});
    const [loadingBoss, setLoadingBoss] = useState(null);

    const handlePhaseChange = (phaseId) => {
        setSelectedPhase(phaseId);
        const firstRaidInPhase = raids[phaseId]?.[0]?.id ?? null;
        setSelectedRaid(firstRaidInPhase);
        setLoadedItems({}); // Clear cached items for new raid
        setLoadingBoss(null);

        if (firstRaidInPhase) {
            router.visit(route('loot.index', { raid_id: firstRaidInPhase }), {
                only: ['selected_raid_id'],
                preserveState: true,
                preserveScroll: true,
            });
        }
    };

    const handleRaidChange = (raidId) => {
        setSelectedRaid(raidId);
        setLoadedItems({}); // Clear cached items for new raid
        setLoadingBoss(null);

        router.visit(route('loot.index', { raid_id: raidId }), {
            only: ['selected_raid_id'],
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleBossExpand = (bossId) => {
        if (loadedItems[bossId] || loadingBoss === bossId) {
            return; // Already loaded or currently loading
        }

        setLoadingBoss(bossId);

        router.reload({
            only: ['boss_items'],
            data: { boss_id: bossId },
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const bossItemsData = page.props.boss_items;
                if (bossItemsData?.boss_id) {
                    setLoadedItems(prev => ({
                        ...prev,
                        [bossItemsData.boss_id]: bossItemsData.items,
                    }));
                }
                setLoadingBoss(null);
            },
            onError: () => {
                setLoadingBoss(null);
            },
        });
    };

    const currentRaids = raids[selectedPhase] ?? [];
    const currentBosses = bosses[selectedRaid] ?? [];

    const getItemsForBoss = (bossId) => {
        return loadedItems[bossId] ?? [];
    };

    return (
        <Master title="Loot Bias">
            <LootPageHeader title="Loot Bias" />
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
                        <BossCollapse
                            key={`${selectedRaid}-${boss.id}`}
                            title={boss.name}
                            bossId={boss.id}
                            onExpand={handleBossExpand}
                            loading={loadingBoss === boss.id}
                        >
                            <BossItems items={getItemsForBoss(boss.id)} />
                        </BossCollapse>
                    ))}
                    <BossCollapse
                        key={`${selectedRaid}-trash`}
                        title="Trash drops"
                        bossId={-1}
                        onExpand={handleBossExpand}
                        loading={loadingBoss === -1}
                    >
                        <BossItems items={getItemsForBoss(-1)} grouped={false} />
                    </BossCollapse>
                </div>
            </main>
        </Master>
    );
}
