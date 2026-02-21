import Master from "@/Layouts/Master";
import { useState, useRef, useEffect } from "react";
import { router, Link, Deferred } from "@inertiajs/react";
import Collapsible from "@/Components/Collapsible";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";


function BossItemsSkeleton() {
    return (
        <div className="animate-pulse space-y-2">
            {[1, 2, 3].map((i) => (
                <div key={i} className="h-12 rounded bg-amber-600/20" />
            ))}
        </div>
    );
}

function BossesSkeleton() {
    return (
        <div className="flex animate-pulse flex-col gap-2">
            {[1, 2, 3, 4, 5].map((i) => (
                <div key={i} className="h-14 rounded-md border border-amber-600/30 bg-amber-600/10" />
            ))}
        </div>
    );
}

function BossesList({
    bosses,
    selectedRaid,
    loadedBoss,
    onBossExpand,
    getItemsForBoss,
}) {
    const currentBosses = bosses[selectedRaid] ?? [];

    return (
        <div className="flex flex-col gap-2">
            {currentBosses.map((boss) => {
                const isTrash = boss.id < 0;
                return (
                    <Collapsible
                        key={`${selectedRaid}-${boss.id}`}
                        title={boss.name}
                        onExpand={() => onBossExpand(boss.id)}
                        loading={loadedBoss === boss.id}
                        skeleton={<BossItemsSkeleton />}
                        sessionKey={`loot_bias_expanded_${selectedRaid}_${boss.id}`}
                        className="border-amber-600"
                        headerClassName="hover:bg-amber-600/10"
                        bodyClassName="border-amber-600"
                        headerRight={
                            boss.comments_count > 0 && (
                                <span className="inline-flex items-center gap-1 rounded bg-amber-600/20 px-2 py-1 text-xs font-semibold text-amber-600">
                                    <Icon icon="comments" style="solid" className="h-4 w-4" />
                                    {boss.comments_count}
                                </span>
                            )
                        }
                    >
                        <BossItems items={getItemsForBoss(boss.id)} grouped={!isTrash} />
                    </Collapsible>
                );
            })}
        </div>
    );
}

function PriorityItem({ priority }) {
    return (
        <span className="inline-flex items-center gap-1">
            {priority.media && <img src={priority.media} alt="" className="h-4 w-4" />}
            <span>{priority.title}</span>
        </span>
    );
}

function PriorityDisplay({ priorities }) {
    if (!priorities || priorities.length === 0) {
        return <p className="text-center italic text-gray-500 lg:text-right">Item not subject to loot council.</p>;
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
        <span className="flex flex-col items-center gap-1 lg:flex-row lg:justify-end">
            {weights.map((weight, weightIndex) => (
                <span key={weight} className="flex flex-col items-center gap-1 lg:flex-row">
                    {weightIndex > 0 && <span className="mx-1 text-xl font-bold text-amber-600">&gt;</span>}
                    {grouped[weight].map((priority, index) => (
                        <span key={priority.id} className="flex flex-col items-center gap-1 lg:flex-row">
                            {index > 0 && <span className="mx-1 text-xl font-bold text-amber-600">=</span>}
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
        <Link
            href={route("loot.items.show", { item: item.id, name: item.slug })}
            className="flex flex-wrap items-center gap-4 rounded bg-brown-800/50 p-2 transition-colors hover:bg-brown-800/70"
        >
            {item.icon && (
                <a
                    href={route("loot.items.show", { item: item.id, name: item.slug })}
                    data-wowhead={`item=${item.id}&domain=tbc`}
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <img
                        src={item.icon}
                        alt={item.name}
                        className="h-8 w-8 rounded"
                        data-wowhead={`item=${item.id}&domain=tbc`}
                    />
                </a>
            )}
            <div className="w-48 flex-initial text-left lg:flex-1">
                <h4 className="text-md mb-1 font-bold">{item.name}</h4>
                <div className="flex flex-col items-start gap-1 lg:flex-row lg:items-center lg:gap-2">
                    <p className="text-sm text-gray-400">Item ID: {item.id}</p>
                    {item.commentsCount > 0 && (
                        <p className="inline-flex items-center gap-1 text-xs">
                            <Icon icon="comments" style="solid" className="h-3 w-3" />
                            {item.commentsCount} comment{item.commentsCount > 1 ? "s" : ""}
                        </p>
                    )}
                    {item.hasNotes && (
                        <p className="inline-flex items-center gap-1 text-xs">
                            <Icon icon="sticky-note" style="solid" className="h-3 w-3" />
                            Notes
                        </p>
                    )}
                </div>
            </div>
            <div className="mx-auto flex-auto lg:mb-0 lg:mr-0">
                <PriorityDisplay priorities={item.priorities} />
            </div>
        </Link>
    );
}

function BossItems({ items, grouped = true }) {
    if (!items || items.length === 0) {
        return <p className="italic text-gray-500">No items configured for this boss.</p>;
    }

    if (!grouped) {
        return (
            <div className="space-y-2">
                {items.map((item) => (
                    <ItemRow key={item.id} item={item} />
                ))}
            </div>
        );
    }

    // Separate grouped and ungrouped items
    const groupedItems = items.filter((item) => item.group);
    const ungroupedItems = items.filter((item) => !item.group).sort((a, b) => a.name.localeCompare(b.name));

    // Group items by their group name and sort within each group
    const groups = groupedItems.reduce((acc, item) => {
        const groupName = item.group;
        if (!acc[groupName]) {
            acc[groupName] = [];
        }
        acc[groupName].push(item);
        return acc;
    }, {});

    // Sort items within each group by name
    Object.keys(groups).forEach((groupName) => {
        groups[groupName].sort((a, b) => a.name.localeCompare(b.name));
    });

    const groupNames = Object.keys(groups);

    return (
        <div className="space-y-4">
            {groupNames.map((groupName) => (
                <div key={groupName} className="mb-8 space-y-2">
                    <h4 className="text-sm font-semibold text-amber-500">{groupName}</h4>
                    {groups[groupName].map((item) => (
                        <ItemRow key={item.id} item={item} />
                    ))}
                </div>
            ))}
            {ungroupedItems.length > 0 && (
                <div className="space-y-2">
                    {ungroupedItems.map((item) => (
                        <ItemRow key={item.id} item={item} />
                    ))}
                </div>
            )}
        </div>
    );
}

function MegaMenu({ phases, raids, selectedPhase, selectedRaid, onPhaseSelect, onPhaseChange, onRaidChange }) {
    const [phaseOpen, setPhaseOpen] = useState(false);
    const [raidOpen, setRaidOpen] = useState(false);
    const lastPhaseTapRef = useRef({ id: null, time: 0 });
    const singleTapTimeoutRef = useRef(null);

    useEffect(() => {
        return () => clearTimeout(singleTapTimeoutRef.current);
    }, []);

    const currentRaids = raids[selectedPhase] ?? [];
    const currentPhase = phases.find((p) => p.id === selectedPhase);
    const currentRaid = currentRaids.find((r) => r.id === selectedRaid);

    const handlePhaseTap = (phaseId) => {
        const now = Date.now();

        if (lastPhaseTapRef.current.id === phaseId && now - lastPhaseTapRef.current.time < 500) {
            clearTimeout(singleTapTimeoutRef.current);
            lastPhaseTapRef.current = { id: null, time: 0 };
            onPhaseChange(phaseId);
            setPhaseOpen(false);
            return;
        }

        lastPhaseTapRef.current = { id: phaseId, time: now };
        clearTimeout(singleTapTimeoutRef.current);
        singleTapTimeoutRef.current = setTimeout(() => {
            onPhaseSelect(phaseId);
            setPhaseOpen(false);
            lastPhaseTapRef.current = { id: null, time: 0 };
        }, 300);
    };

    const handleRaidTap = (raidId) => {
        onRaidChange(raidId);
        setRaidOpen(false);
    };

    return (
        <div className="animate-in fade-in mb-8 flex flex-col gap-3 duration-300 md:hidden">
            {/* Phase dropdown */}
            <div className="relative">
                <button
                    type="button"
                    onClick={() => {
                        setPhaseOpen(!phaseOpen);
                        setRaidOpen(false);
                    }}
                    aria-expanded={phaseOpen}
                    className={`flex w-full items-center justify-between rounded border border-amber-600 px-4 py-2 transition-colors ${
                        phaseOpen ? "bg-amber-600 text-white" : "text-amber-600 hover:bg-amber-600/20"
                    }`}
                >
                    <span>{currentPhase ? `Phase ${currentPhase.id}` : "Select Phase"}</span>
                    <Icon
                        icon="chevron-down"
                        style="solid"
                        className={`transition-transform duration-300 ${phaseOpen ? "rotate-180" : ""}`}
                    />
                </button>
                {phaseOpen && <div className="fixed inset-0 z-40" onClick={() => setPhaseOpen(false)} />}
                {phaseOpen && (
                    <div className="absolute left-0 z-50 mt-1 w-full rounded-md border border-amber-600 bg-brown shadow-lg">
                        {phases.map((phase) => (
                            <button
                                key={phase.id}
                                type="button"
                                onClick={() => handlePhaseTap(phase.id)}
                                className={`w-full px-4 py-2 text-left transition-colors ${
                                    selectedPhase === phase.id
                                        ? "bg-amber-600/30 text-white"
                                        : "text-amber-600 hover:bg-amber-600/10"
                                }`}
                            >
                                Phase {phase.id}
                            </button>
                        ))}
                    </div>
                )}
            </div>

            {/* Raid dropdown */}
            <div className="relative">
                <button
                    type="button"
                    onClick={() => {
                        setRaidOpen(!raidOpen);
                        setPhaseOpen(false);
                    }}
                    aria-expanded={raidOpen}
                    className={`flex w-full items-center justify-between rounded border border-amber-600 px-4 py-2 transition-colors ${
                        raidOpen ? "bg-amber-600 text-white" : "text-amber-600 hover:bg-amber-600/20"
                    }`}
                >
                    <span>{currentRaid ? currentRaid.name : "Select Raid"}</span>
                    <Icon
                        icon="chevron-down"
                        style="solid"
                        className={`transition-transform duration-300 ${raidOpen ? "rotate-180" : ""}`}
                    />
                </button>
                {raidOpen && <div className="fixed inset-0 z-40" onClick={() => setRaidOpen(false)} />}
                {raidOpen && (
                    <div className="absolute left-0 z-50 mt-1 w-full rounded-md border border-amber-600 bg-brown shadow-lg">
                        {currentRaids.map((raid) => (
                            <button
                                key={raid.id}
                                type="button"
                                onClick={() => handleRaidTap(raid.id)}
                                className={`w-full px-4 py-2 text-left transition-colors ${
                                    selectedRaid === raid.id
                                        ? "bg-amber-600/30 text-white"
                                        : "text-amber-600 hover:bg-amber-600/10"
                                }`}
                            >
                                {raid.name}
                            </button>
                        ))}
                    </div>
                )}
            </div>
        </div>
    );
}

export default function Index({ phases, current_phase, raids, bosses, selected_raid_id, boss_items }) {
    const [selectedPhase, setSelectedPhase] = useState(current_phase);
    const [selectedRaid, setSelectedRaid] = useState(selected_raid_id);
    const [loadedItems, setLoadedItems] = useState({});
    const [loadedBoss, setloadedBoss] = useState(null);

    // Refs to access current values inside callbacks and effects without stale closures
    const loadedItemsRef = useRef(loadedItems);
    loadedItemsRef.current = loadedItems;
    const loadedBossRef = useRef(null);
    loadedBossRef.current = loadedBoss;

    // Queue of boss IDs waiting to have their items fetched
    const loadQueueRef = useRef([]);

    // Stable ref to the fetch function so it can be called from the queue effect
    const doLoadBossRef = useRef(null);
    doLoadBossRef.current = (bossId) => {
        loadedBossRef.current = bossId;
        setloadedBoss(bossId);
        router.reload({
            only: ["boss_items"],
            data: { boss_id: bossId },
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const bossItemsData = page.props.boss_items.data;
                if (bossItemsData?.bossId) {
                    setLoadedItems((prev) => ({
                        ...prev,
                        [bossItemsData.bossId]: bossItemsData.items,
                    }));
                }
                loadedBossRef.current = null;
                setloadedBoss(null);
            },
            onError: () => {
                loadedBossRef.current = null;
                setloadedBoss(null);
            },
        });
    };

    // When a load completes, process the next queued boss
    useEffect(() => {
        if (loadedBoss !== null) {
            return;
        }
        while (loadQueueRef.current.length > 0) {
            const nextBossId = loadQueueRef.current.shift();
            if (!loadedItemsRef.current[nextBossId]) {
                doLoadBossRef.current(nextBossId);
                break;
            }
        }
    }, [loadedBoss]);

    const handlePhaseChange = (phaseId) => {
        setSelectedPhase(phaseId);
        const firstRaidInPhase = raids[phaseId]?.[0]?.id ?? null;
        setSelectedRaid(firstRaidInPhase);
        setLoadedItems({});
        setloadedBoss(null);
        loadQueueRef.current = [];

        if (firstRaidInPhase) {
            router.visit(route("loot.phase", { phase: phaseId, raid_id: firstRaidInPhase }), {
                only: ["selected_raid_id", "bosses"],
                preserveState: true,
                preserveScroll: true,
            });
        }
    };

    const handlePhaseSelect = (phaseId) => {
        setSelectedPhase(phaseId);
        const firstRaidInPhase = raids[phaseId]?.[0]?.id ?? null;
        setSelectedRaid(firstRaidInPhase);
        setLoadedItems({});
        setloadedBoss(null);
        loadQueueRef.current = [];
    };

    const handleRaidChange = (raidId) => {
        setSelectedRaid(raidId);
        setLoadedItems({});
        setloadedBoss(null);
        loadQueueRef.current = [];

        router.visit(route("loot.phase", { phase: selectedPhase, raid_id: raidId }), {
            only: ["selected_raid_id", "bosses"],
            preserveState: true,
            preserveScroll: true,
        });
    };

    const handleBossExpand = (bossId) => {
        if (loadedItemsRef.current[bossId] || loadedBossRef.current === bossId) {
            return;
        }

        if (loadedBossRef.current !== null) {
            if (!loadQueueRef.current.includes(bossId)) {
                loadQueueRef.current.push(bossId);
            }
            return;
        }

        doLoadBossRef.current(bossId);
    };

    const currentRaids = raids[selectedPhase] ?? [];
    const currentPhase = phases.find((p) => p.id === selectedPhase);

    const getItemsForBoss = (bossId) => {
        return loadedItems[bossId] ?? [];
    };

    return (
        <Master title="Loot Bias">
            <SharedHeader backgroundClass="bg-karazhan" title="Loot Bias" />
            {/* Content */}
            <main className="container mx-auto px-4 py-8">
                {/* Mobile navigation */}
                <MegaMenu
                    phases={phases}
                    raids={raids}
                    selectedPhase={selectedPhase}
                    selectedRaid={selectedRaid}
                    onPhaseSelect={handlePhaseSelect}
                    onPhaseChange={handlePhaseChange}
                    onRaidChange={handleRaidChange}
                />

                {/* Desktop navigation */}
                <div className="animate-in fade-in mb-8 hidden flex-wrap gap-2 duration-300 md:flex">
                    {phases.map((phase) => (
                        <button
                            key={phase.id}
                            onClick={() => handlePhaseChange(phase.id)}
                            className={`rounded border px-4 py-2 transition-colors ${
                                selectedPhase === phase.id
                                    ? "border-amber-600 bg-amber-600 text-white"
                                    : "border-amber-600 text-amber-600 hover:bg-amber-600/20"
                            }`}
                            title={`View loot for phase ${phase.id}`}
                        >
                            Phase {phase.id}
                        </button>
                    ))}
                </div>
                <div className="mb-8 hidden flex-wrap gap-2 md:flex">
                    {currentRaids.map((raid) => (
                        <button
                            key={raid.id}
                            onClick={() => handleRaidChange(raid.id)}
                            className={`rounded border px-4 py-2 transition-colors ${
                                selectedRaid === raid.id
                                    ? "border-amber-600 bg-amber-600 text-white"
                                    : "border-amber-600 text-amber-600 hover:bg-amber-600/20"
                            }`}
                        >
                            {raid.name}
                        </button>
                    ))}
                </div>
                {currentPhase?.start_date && (
                    <p className="mb-4 text-sm text-gray-400">
                        Phase {currentPhase.id}{" "}
                        {new Date(currentPhase.start_date) > new Date() ? "starts on" : "started"}{" "}
                        {new Date(currentPhase.start_date).toLocaleDateString(undefined, {
                            year: "numeric",
                            month: "long",
                            day: "numeric",
                        })}
                    </p>
                )}
                <Deferred data="bosses" fallback={<BossesSkeleton />}>
                    <BossesList
                        bosses={bosses}
                        selectedRaid={selectedRaid}
                        loadedBoss={loadedBoss}
                        onBossExpand={handleBossExpand}
                        getItemsForBoss={getItemsForBoss}
                    />
                </Deferred>
            </main>
        </Master>
    );
}
