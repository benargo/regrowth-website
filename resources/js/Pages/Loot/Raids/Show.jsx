import Master from "@/Layouts/Master";
import { useState, useRef, useEffect } from "react";
import { router, Link } from "@inertiajs/react";
import Collapsible from "@/Components/Collapsible";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";
import PageContainer from "@/Components/PageContainer";
import ToolNav from "@/Components/ToolNav";
import ItemRow from "@/Components/Loot/ItemRow";

function prepareItems(rawItems) {
    const [groupedItems, ungroupedItems] = rawItems.reduce(
        ([g, u], item) => (item.group ? [[...g, item], u] : [g, [...u, item]]),
        [[], []],
    );
    ungroupedItems.sort((a, b) => a.name.localeCompare(b.name));

    const groups = groupedItems.reduce((acc, item) => {
        if (!acc[item.group]) {
            acc[item.group] = [];
        }
        acc[item.group].push(item);
        return acc;
    }, {});

    Object.values(groups).forEach((group) => group.sort((a, b) => a.name.localeCompare(b.name)));

    return { groups, ungroupedItems };
}

function BossItems({ prepared, weightThreshold }) {
    if (!prepared) {
        return <p className="text-gray-500 italic">No items configured for this boss.</p>;
    }

    const { groups, ungroupedItems } = prepared;
    const groupNames = Object.keys(groups);

    if (groupNames.length === 0) {
        return (
            <div className="space-y-2">
                {ungroupedItems.map((item) => (
                    <ItemRow key={item.id} item={item} weightThreshold={weightThreshold} />
                ))}
            </div>
        );
    }

    return (
        <div className="space-y-4">
            {groupNames.map((groupName) => (
                <div key={groupName} className="mb-8 space-y-2">
                    <h4 className="text-sm font-semibold text-amber-500">{groupName}</h4>
                    {groups[groupName].map((item) => (
                        <ItemRow key={item.id} item={item} weightThreshold={weightThreshold} />
                    ))}
                </div>
            ))}
            {ungroupedItems.length > 0 && (
                <div className="space-y-2">
                    {ungroupedItems.map((item) => (
                        <ItemRow key={item.id} item={item} weightThreshold={weightThreshold} />
                    ))}
                </div>
            )}
        </div>
    );
}

export default function Index({ raid, boss_items, trash_items, priority_weight_threshold }) {
    const bosses = [...(raid.data.bosses ?? [])].sort(
        (a, b) => (a.encounter_order ?? 999) - (b.encounter_order ?? 999),
    );
    const [loadedItems, setLoadedItems] = useState(() => {
        const initial = {};
        if (boss_items) {
            Object.entries(boss_items).forEach(([bossId, collection]) => {
                if (collection?.data) {
                    initial[bossId] = prepareItems(collection.data);
                }
            });
        }
        return initial;
    });
    const [loadedBoss, setloadedBoss] = useState(null);
    const [trashItems, setTrashItems] = useState(trash_items?.data ? prepareItems(trash_items.data) : null);
    const [loadingTrash, setLoadingTrash] = useState(false);

    // Refs to access current values inside callbacks and effects without stale closures
    const loadedItemsRef = useRef(loadedItems);
    loadedItemsRef.current = loadedItems;
    const loadedBossRef = useRef(null);
    loadedBossRef.current = loadedBoss;

    // Queue of boss IDs waiting to have their items fetched
    const loadQueueRef = useRef(new Set());

    // Stable ref to the fetch function so it can be called from the queue effect
    const doLoadBossRef = useRef(null);
    doLoadBossRef.current = (bossId) => {
        loadedBossRef.current = bossId;
        setloadedBoss(bossId);
        router.reload({
            only: [`boss_items.${bossId}`],
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                const items = page.props.boss_items?.[bossId]?.data ?? [];
                setLoadedItems((prev) => ({ ...prev, [bossId]: prepareItems(items) }));
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
        while (loadQueueRef.current.size > 0) {
            const [nextBossId] = loadQueueRef.current;
            loadQueueRef.current.delete(nextBossId);
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
            loadQueueRef.current.add(bossId);
            return;
        }

        doLoadBossRef.current(bossId);
    };

    const handleTrashExpand = () => {
        if (trashItems !== null || loadingTrash) {
            return;
        }
        setLoadingTrash(true);
        router.reload({
            only: ["trash_items"],
            preserveState: true,
            preserveScroll: true,
            onSuccess: (page) => {
                setTrashItems(prepareItems(page.props.trash_items?.data ?? []));
                setLoadingTrash(false);
            },
            onError: () => setLoadingTrash(false),
        });
    };

    const getItemsForBoss = (bossId) => {
        return loadedItems[bossId] ?? null;
    };

    return (
        <Master title={`Loot Bias - ${raid.data.name}`}>
            <SharedHeader backgroundClass={raid.data.background ?? "bg-ssctk"} title="Loot Bias" subtitle={raid.data.name} />
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
                <div className="flex flex-col gap-4">
                    {bosses.map((boss) => (
                        <Collapsible
                            key={boss.id}
                            title={boss.name}
                            onExpand={() => handleBossExpand(boss.id)}
                            loading={loadedBoss === boss.id}
                            sessionKey={`loot_bias_expanded_${boss.id}`}
                            style="amber"
                            headerRight={
                                boss.comments_count > 0 && (
                                    <span className="inline-flex items-center gap-1 rounded bg-amber-600/20 px-2 py-1 text-xs font-semibold text-amber-600">
                                        <Icon icon="comments" style="solid" className="h-4 w-4" />
                                        {boss.comments_count}
                                    </span>
                                )
                            }
                        >
                            <BossItems prepared={getItemsForBoss(boss.id)} weightThreshold={priority_weight_threshold} />
                        </Collapsible>
                    ))}
                </div>
                {raid.data.has_trash_items && (
                    <div className="mt-6">
                        <Collapsible
                            title="Trash"
                            onExpand={handleTrashExpand}
                            loading={loadingTrash}
                            sessionKey={`loot_bias_expanded_trash_${raid.data.id}`}
                            style="amber"
                            headerRight={
                                raid.data.trash_comments_count > 0 && (
                                    <span className="inline-flex items-center gap-1 rounded bg-amber-600/20 px-2 py-1 text-xs font-semibold text-amber-600">
                                        <Icon icon="comments" style="solid" className="h-4 w-4" />
                                        {raid.data.trash_comments_count}
                                    </span>
                                )
                            }
                        >
                            <BossItems prepared={trashItems} weightThreshold={priority_weight_threshold} />
                        </Collapsible>
                    </div>
                )}
            </PageContainer>
        </Master>
    );
}
