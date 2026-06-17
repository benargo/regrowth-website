import Master from "@/Layouts/Master";
import { useState, useRef, useEffect } from "react";
import { router, Link, Deferred } from "@inertiajs/react";
import Collapsible from "@/Components/Collapsible";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import PageContainer from "@/Components/PageContainer";
import ToolNav from "@/Components/ToolNav";

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

function BossesList({ bosses, loadedBoss, onBossExpand, getItemsForBoss }) {
    return (
        <div className="flex flex-col gap-4">
            {bosses.map((boss) => {
                const isTrash = boss.id < 0;
                return (
                    <Collapsible
                        key={boss.id}
                        title={boss.name}
                        onExpand={() => onBossExpand(boss.id)}
                        loading={loadedBoss === boss.id}
                        skeleton={<BossItemsSkeleton />}
                        sessionKey={`loot_bias_expanded_${boss.id}`}
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
        return <p className="text-center text-gray-500 italic lg:text-right">MS &gt; OS</p>;
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
    const href = route("loot.items.show", { item: item.id, name: item.slug });

    return (
        <div
            onClick={() => router.visit(href)}
            className="bg-brown-800/50 hover:bg-brown-800/70 flex cursor-pointer flex-wrap items-center gap-4 rounded p-2 transition-colors"
        >
            {item.icon && (
                <a
                    href={href}
                    data-wowhead={`item=${item.id}&domain=tbc`}
                    target="_blank"
                    rel="noopener noreferrer"
                    onClick={(e) => e.stopPropagation()}
                >
                    <img src={item.icon} alt={item.name} className="h-8 w-8 rounded" />
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
            <div className="mx-auto flex-auto lg:mr-0 lg:mb-0">
                <PriorityDisplay priorities={item.priorities} />
            </div>
        </div>
    );
}

function BossItems({ items, grouped = true }) {
    if (!items || items.length === 0) {
        return <p className="text-gray-500 italic">No items configured for this boss.</p>;
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

export default function Index({ bosses, boss_items }) {
    const [loadedItems, setLoadedItems] = useState(() => {
        if (boss_items?.data?.bossId != null) {
            return { [boss_items.data.bossId]: boss_items.data.items ?? [] };
        }
        return {};
    });
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
                const bossItems = page.props.boss_items;
                if (bossItems?.data?.bossId != null) {
                    // BossItemsResource shape: { data: { bossId, items: [...] } }
                    setLoadedItems((prev) => ({
                        ...prev,
                        [bossItems.data.bossId]: bossItems.data.items ?? [],
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

    const getItemsForBoss = (bossId) => {
        return loadedItems[bossId] ?? [];
    };

    return (
        <Master title="Loot Bias">
            <SharedHeader backgroundClass="bg-ssctk" title="Loot Bias" />
            <ToolNav>
                <Link
                    href={route("loot.index")}
                    className="hover:border-primary hover:bg-brown-800 active:border-primary my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white"
                >
                    <Icon icon="arrow-left" style="solid" className="mr-2" />
                    <span>Loot bias tool</span>
                </Link>
            </ToolNav>
            {/* Content */}
            <PageContainer>
                <Deferred data="bosses" fallback={<BossesSkeleton />}>
                    <BossesList
                        bosses={bosses}
                        loadedBoss={loadedBoss}
                        onBossExpand={handleBossExpand}
                        getItemsForBoss={getItemsForBoss}
                    />
                </Deferred>
            </PageContainer>
        </Master>
    );
}
