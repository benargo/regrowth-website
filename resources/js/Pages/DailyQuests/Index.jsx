import { Deferred, Link, usePage } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";

function QuestsSkeleton() {
    return (
        <div className="space-y-6">
            {[...Array(5)].map((_, i) => (
                <div key={i} className="animate-pulse rounded-lg bg-brown-800 p-4">
                    <div className="mb-3 h-5 w-32 rounded bg-brown-700" />
                    <div className="flex items-center gap-3">
                        <div className="h-10 w-10 rounded bg-brown-700" />
                        <div className="h-4 w-48 rounded bg-brown-700" />
                    </div>
                </div>
            ))}
        </div>
    );
}

function QuestReward({ reward }) {
    return (
        <div className="flex items-center gap-3">
            <Link
                href={reward.wowhead_url}
                data-wowhead={`item=${reward.item_id}&domain=tbc`}
                target="_blank"
                rel="noopener noreferrer"
                className="relative flex-none"
            >
                <img
                    src={reward.icon}
                    alt={reward.name}
                    className="box-shadow h-10 w-10 rounded"
                />
                {reward.quantity > 1 && (
                    <span className="absolute bottom-0 right-0 rounded bg-black/75 px-1 text-xs font-bold text-white">
                        {reward.quantity}
                    </span>
                )}
            </Link>
            <Link
                href={reward.wowhead_url}
                data-wowhead={`item=${reward.item_id}&domain=tbc`}
                target="_blank"
                rel="noopener noreferrer"
                className={`text-sm font-medium text-quality-${reward.quality}`}
            >
                {reward.name}
            </Link>
        </div>
    );
}

function QuestCard({ quest }) {
    const hasMultipleRewards = quest.rewards.length > 1;

    return (
        <div className="mb-8 w-full rounded-lg bg-brown-800/50 p-6">
            <h3 className="mb-1 text-2xl font-bold text-amber-400">
                {quest.icon && (
                    <img
                        src={quest.icon}
                        alt={quest.label}
                        className="inline-block h-6 w-6 mr-2"
                    />
                )}
                {quest.label}
            </h3>
            <p className="mb-2 text-md text-gray-300">{quest.instance ? `${quest.instance} - ${quest.name}` : quest.name}</p>
            <div>
                {hasMultipleRewards && (
                    <p className="mb-2 text-xs italic text-gray-400">
                        A choice from one of the following:
                    </p>
                )}
                <div className="flex flex-wrap gap-4">
                    {quest.rewards.map((reward) => (
                        <QuestReward key={reward.item_id} reward={reward} />
                    ))}
                </div>
            </div>
        </div>
    );
}

function QuestsList() {
    const { quests } = usePage().props;

    if (!quests) {
        return (
            <p className="text-center text-gray-400">
                No daily quests have been posted yet today.
            </p>
        );
    }

    return (
        <div className="space-y-6">
            {quests.map((quest, index) => (
                <QuestCard key={index} quest={quest} />
            ))}
        </div>
    );
}

export default function Index({ hasNotification }) {
    return (
        <Master title="Today's Daily Quests">
            <SharedHeader
                title="Today's Daily Quests"
                backgroundClass="bg-dungeons"
            />
            <div className="container mx-auto px-4 py-8">
                {hasNotification ? (
                    <Deferred data="quests" fallback={<QuestsSkeleton />}>
                        <QuestsList />
                    </Deferred>
                ) : (
                    <p className="text-center text-gray-400">
                        No daily quests have been posted yet today.
                    </p>
                )}
            </div>
        </Master>
    );
}
