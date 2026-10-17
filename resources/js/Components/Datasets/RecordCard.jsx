import { useId } from "react";
import { Button } from "@headlessui/react";
import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import { modelBackground } from "@/Helpers/ModelColor";

export const focusRing = "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400";

/**
 * Turns a record's usage counts into phrases like "3 phases", skipping zeros.
 * Each label is [countKey, singular, plural].
 */
export function usageSummary(record, labels) {
    return labels
        .filter(([key]) => record[key] > 0)
        .map(([key, singular, plural]) => `${record[key]} ${record[key] === 1 ? singular : plural}`);
}

/**
 * A record card for a dataset's Index page, with Edit and Delete actions.
 * Delete is disabled while the record is in use; omit `usage` or `onDelete`
 * to hide the usage sentence or the Delete button. `headerClassName` adds a
 * background class (e.g. raid artwork), `badge` a status label beside the
 * title, `handle` a drag handle before it, and `headingLevel` sets the
 * title's heading level.
 */
export default function RecordCard({
    title,
    subtitle,
    headerColor,
    headerImage,
    headerClassName = "",
    badge = null,
    handle = null,
    headingLevel = 2,
    details,
    usage = null,
    links,
    editLabel = "Edit",
    editIcon = "edit",
    onDelete = null,
}) {
    const usageId = useId();
    const header = modelBackground(headerColor, headerImage);
    const hasUsage = usage !== null;
    const isInUse = hasUsage && usage.length > 0;
    const Heading = `h${headingLevel}`;

    return (
        <article className="bg-brown-800/60 overflow-hidden rounded border border-amber-600/40">
            <header className={`${header.className} ${headerClassName} bg-cover bg-center`} style={header.style}>
                <div className="flex items-start gap-3 bg-linear-to-r from-black/85 to-black/40 px-4 py-5">
                    {handle}
                    <div className="flex min-w-0 flex-1 flex-col gap-1">
                        <Heading className="font-serif text-2xl text-white">{title}</Heading>
                        {subtitle && <p className="text-sm text-gray-300">{subtitle}</p>}
                    </div>
                    {badge && (
                        <span className="bg-brown-900/90 shrink-0 rounded px-2 py-1 text-xs font-semibold text-white ring-1 ring-amber-400">
                            {badge}
                        </span>
                    )}
                </div>
            </header>

            <div className="flex flex-col gap-4 p-4">
                <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {details.map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-sm text-gray-300">{label}</dt>
                            <dd className="text-white">{value ?? "Not set"}</dd>
                        </div>
                    ))}
                </dl>

                {hasUsage && (
                    <p id={usageId} className="text-sm text-gray-300">
                        {isInUse
                            ? `Used by ${usage.join(", ")}. Remove or reassign these before deleting.`
                            : "Not used by any records yet."}
                    </p>
                )}

                <div className="flex flex-wrap gap-2">
                    <Link
                        href={links.edit}
                        className={`inline-flex items-center gap-1.5 rounded border border-amber-500 px-3 py-1.5 text-sm text-white hover:bg-amber-600/30 ${focusRing}`}
                    >
                        <Icon icon={editIcon} style="light" />
                        {editLabel}
                        <span className="sr-only"> {title}</span>
                    </Link>
                    {onDelete && (
                        <Button
                            disabled={isInUse}
                            aria-describedby={hasUsage ? usageId : undefined}
                            onClick={onDelete}
                            className="inline-flex items-center gap-1.5 rounded border border-red-400 px-3 py-1.5 text-sm text-red-300 data-disabled:cursor-not-allowed data-disabled:border-gray-600 data-disabled:text-gray-400 data-focus:outline-2 data-focus:outline-offset-2 data-focus:outline-red-400 data-hover:bg-red-600/20"
                        >
                            <Icon icon="trash" style="light" />
                            Delete<span className="sr-only"> {title}</span>
                        </Button>
                    )}
                </div>
            </div>
        </article>
    );
}
