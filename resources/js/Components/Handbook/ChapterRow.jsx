import { Link } from "@inertiajs/react";
import ChapterTitle from "@/Components/Discord/ChapterTitle";
import { focusRing } from "@/Components/Datasets/RecordCard";
import Icon from "@/Components/FontAwesome/Icon";
import { DISCORD_MESSAGE_LIMIT } from "@/Helpers/discordTags";
import formatDate from "@/Helpers/FormatDate";

/**
 * A raid handbook chapter's row on the officers' Index page: its drag
 * handle (or an empty slot of the same width), title, character count, last
 * update and an Edit link. `lookup` is the result of `buildMentionLookup`.
 *
 * @param {{ chapter: Object, lookup: Object, dragHandle?: import("react").ReactNode }} props
 */
export default function ChapterRow({ chapter, lookup, dragHandle = null }) {
    const updated = formatDate(chapter.updated_at);

    return (
        <article className="bg-brown-800/60 flex flex-wrap items-center gap-x-4 gap-y-2 rounded border border-amber-600/40 px-3 py-3">
            <span className="flex w-14 shrink-0 items-center">{dragHandle}</span>
            <ChapterTitle
                chapter={chapter}
                lookup={lookup}
                as="h3"
                className="min-w-0 flex-1 font-serif text-xl text-white"
            />
            <dl className="flex shrink-0 gap-4 text-sm text-gray-300">
                <div className="flex gap-1">
                    <dt className="sr-only">Length</dt>
                    <dd className="tabular-nums">
                        {chapter.character_count.toLocaleString("en-GB")} /{" "}
                        {DISCORD_MESSAGE_LIMIT.toLocaleString("en-GB")}
                    </dd>
                </div>
                <div className="flex gap-1">
                    <dt>Updated</dt>
                    <dd>
                        <time dateTime={chapter.updated_at}>{updated.medium}</time>
                    </dd>
                </div>
            </dl>
            <Link
                href={route("management.handbook.edit", chapter.slug)}
                className={`inline-flex shrink-0 items-center gap-1.5 rounded border border-amber-500 px-3 py-1.5 text-sm text-white hover:bg-amber-600/30 ${focusRing}`}
            >
                <Icon icon="edit" style="light" />
                Edit<span className="sr-only"> {chapter.title}</span>
            </Link>
        </article>
    );
}
