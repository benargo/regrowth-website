import Master from "@/Layouts/Master";
import { Link } from "@inertiajs/react";
import CommentItem from "@/Components/Loot/CommentItem";
import Icon from "@/Components/FontAwesome/Icon";
import Pagination from "@/Components/Pagination";
import SharedHeader from "@/Components/SharedHeader";

export default function Comments({ comments }) {
    // Group comments by item on the client side
    const groupedComments = comments.data.reduce((groups, comment) => {
        const itemId = comment.item?.id ?? comment.item;
        if (!groups[itemId]) {
            groups[itemId] = {
                item: comment.item,
                comments: [],
            };
        }
        groups[itemId].comments.push(comment);
        return groups;
    }, {});

    return (
        <Master title="All Comments">
            <SharedHeader backgroundClass="bg-karazhan" title="Loot Bias" />

            {/* Tool navigation */}
            <nav className="bg-brown-900 shadow">
                <div className="container mx-auto px-4">
                    <div className="flex min-h-12 items-center">
                        <Link
                            href={route("loot.index")}
                            className="my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white hover:border-primary hover:bg-brown-800 active:border-primary"
                        >
                            <Icon icon="arrow-left" style="solid" className="mr-2" />
                            <span>Back to Loot Bias</span>
                        </Link>
                    </div>
                </div>
            </nav>

            {/* Content */}
            <main className="container mx-auto px-4 py-8">
                <h2 className="mb-6 text-xl font-bold">All Comments</h2>

                {comments.data.length > 0 ? (
                    <>
                        <div className="space-y-8">
                            {Object.entries(groupedComments).map(([itemId, group]) => (
                                <section key={itemId}>
                                    <Link
                                        href={route("loot.items.show", { item: group.item.id, name: group.item.slug })}
                                        className="mb-4 flex items-center gap-3 transition-colors hover:text-amber-300"
                                        data-wowhead={`item=${group.item.id}&domain=tbc`}
                                    >
                                        {group.item?.icon && (
                                            <img
                                                src={group.item.icon}
                                                alt={group.item.name}
                                                className="h-8 w-8 rounded"
                                                data-wowhead={`item=${group.item.id}&domain=tbc`}
                                            />
                                        )}
                                        <h3 className="text-lg font-semibold text-amber-400 hover:text-amber-300">
                                            {group.item?.name ?? `Item #${itemId}`}
                                        </h3>
                                    </Link>
                                    <div className="space-y-4">
                                        {group.comments.map((comment) => (
                                            <CommentItem key={comment.id} comment={comment} itemId={group.item.id} />
                                        ))}
                                    </div>
                                </section>
                            ))}
                        </div>
                        <Pagination links={comments.links} meta={comments.meta} itemName="comments" />
                    </>
                ) : (
                    <p className="py-8 text-center text-gray-400">No comments yet.</p>
                )}
            </main>
        </Master>
    );
}
