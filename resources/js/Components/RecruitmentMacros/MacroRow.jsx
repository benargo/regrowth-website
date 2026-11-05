import { Link } from "@inertiajs/react";
import CopyButton from "@/Components/CopyButton";
import { focusRing } from "@/Components/Datasets/RecordCard";
import Icon from "@/Components/FontAwesome/Icon";
import cn from "@/Helpers/cn";
import formatDate from "@/Helpers/FormatDate";

const ACTION_CLASS_NAME = `inline-flex shrink-0 items-center rounded border border-amber-500 px-3 py-1.5 text-sm text-white hover:bg-amber-600/30 ${focusRing}`;

/**
 * A recruitment macro's row on the Index page: its drag handle (or an empty
 * slot of the same width), title, type, character count, creator, a Copy
 * button and, with `canEdit`, an Edit link. The content shows below.
 *
 * @param {{ macro: Object, canEdit: boolean, dragHandle?: import("react").ReactNode }} props
 */
export default function MacroRow({ macro, canEdit, dragHandle = null }) {
    const updated = formatDate(macro.updated_at);

    return (
        <article className="bg-brown-800/60 flex flex-col gap-3 rounded border border-amber-600/40 px-3 py-3">
            <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                <span className="flex w-14 shrink-0 items-center">{dragHandle}</span>
                <h3 className="min-w-0 flex-1 font-serif text-xl text-white">{macro.title}</h3>
                <dl className="flex shrink-0 flex-wrap gap-4 text-sm text-gray-300">
                    <div className="flex gap-1">
                        <dt className="sr-only">Type</dt>
                        <dd className="rounded border border-amber-600/60 px-2 py-0.5 text-xs text-amber-200">
                            {macro.type_label}
                        </dd>
                    </div>
                    <div className="flex gap-1">
                        <dt className="sr-only">Length</dt>
                        <dd className="tabular-nums">
                            {macro.character_count.toLocaleString("en-GB")} /{" "}
                            {macro.character_limit.toLocaleString("en-GB")}
                        </dd>
                    </div>
                    {macro.created_by && (
                        <div className="flex gap-1">
                            <dt>By</dt>
                            <dd>{macro.created_by.display_name}</dd>
                        </div>
                    )}
                    <div className="flex gap-1">
                        <dt>Updated</dt>
                        <dd>
                            <time dateTime={macro.updated_at}>{updated.medium}</time>
                        </dd>
                    </div>
                </dl>
                <CopyButton getValue={macro.content} label="Copy" as="span" className={ACTION_CLASS_NAME} />
                {canEdit && (
                    <Link
                        href={route("management.recruitment-macros.edit", macro.id)}
                        className={cn(ACTION_CLASS_NAME, "gap-1.5")}
                    >
                        <Icon icon="edit" style="light" />
                        Edit<span className="sr-only"> {macro.title}</span>
                    </Link>
                )}
            </div>
            <pre className="bg-brown-900/70 overflow-x-auto rounded border border-amber-600/20 px-3 py-2 font-sans text-sm whitespace-pre-wrap text-gray-200">
                {macro.content}
            </pre>
        </article>
    );
}
