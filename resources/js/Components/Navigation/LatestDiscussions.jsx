import { CloseButton } from "@headlessui/react";
import { Link } from "@inertiajs/react";
import cn from "@/Helpers/cn";

/**
 * The latest root comments on the current game version's items, each with the
 * item's icon when it has one, and an empty state when there are none.
 * `classes` overrides the heading colour.
 */
export default function LatestDiscussions({ navigation, classes = {} }) {
    const discussions = navigation?.latest_discussions ?? [];

    return (
        <section aria-labelledby="latest-discussions-heading">
            <h2
                id="latest-discussions-heading"
                className={cn("mb-2 font-serif text-base text-amber-300", classes.flyoutHeading)}
            >
                Latest discussions
            </h2>

            {discussions.length > 0 ? (
                <ul className="divide-y divide-white/10 border-t border-white/10">
                    {discussions.map((discussion) => (
                        <li key={discussion.id}>
                            <CloseButton
                                as={Link}
                                href={discussion.url}
                                className="-mx-2 flex items-start justify-between gap-6 rounded px-2 py-3 transition-colors hover:bg-white/5 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                            >
                                <span className="flex min-w-0 items-center gap-3">
                                    {discussion.item_icon && (
                                        <span className="relative h-8 w-8 flex-none overflow-hidden rounded border border-white/10 shadow-sm shadow-gray-900">
                                            <img src={discussion.item_icon} alt="" className="h-full w-full" />
                                            <span
                                                className={cn(
                                                    "absolute inset-0 shadow-[inset_0_0_6px_currentColor]",
                                                    discussion.item_quality_class,
                                                )}
                                                aria-hidden="true"
                                            />
                                        </span>
                                    )}
                                    <span className="flex min-w-0 flex-col">
                                        <span
                                            className={cn(
                                                "truncate text-sm font-semibold",
                                                discussion.item_quality_class,
                                            )}
                                        >
                                            {discussion.item_name}
                                        </span>
                                        {discussion.boss_name && (
                                            <span className="truncate text-xs text-gray-400">
                                                {discussion.boss_name}
                                            </span>
                                        )}
                                    </span>
                                </span>
                                <span className="flex flex-none flex-col items-end text-xs whitespace-nowrap text-gray-400">
                                    <span>{discussion.posted_ago}</span>
                                    {discussion.replies_count > 0 && (
                                        <span>
                                            {discussion.replies_count === 1
                                                ? "1 reply"
                                                : `${discussion.replies_count} replies`}
                                        </span>
                                    )}
                                </span>
                            </CloseButton>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-sm text-gray-300">No discussions for this game version yet.</p>
            )}
        </section>
    );
}
