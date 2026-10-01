import { useId } from "react";
import { Button } from "@headlessui/react";
import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";

export const focusRing = "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-ink-400";

/**
 * What a record is used by, as "3 phases"-style phrases. `labels` lists
 * [countKey, singular, plural] for each usage count on the record; counts of
 * zero are left out.
 */
export function usageSummary(record, labels) {
    return labels
        .filter(([key]) => record[key] > 0)
        .map(([key, singular, plural]) => `${record[key]} ${record[key] === 1 ? singular : plural}`);
}

/**
 * One record on a dataset's Index page: a header (with an optional banner
 * background), its [label, value] details, what uses it, and Edit and Delete
 * actions. Delete is disabled while anything still uses the record.
 */
export default function RecordCard({ title, subtitle, headerClassName = "", details, usage, links, onDelete }) {
    const usageId = useId();
    const inUse = usage.length > 0;

    return (
        <article className="border-ink-600/40 bg-ground-800/60 overflow-hidden rounded border">
            <header className={`${headerClassName} bg-cover bg-center`}>
                <div className="flex flex-col gap-1 bg-linear-to-r from-black/85 to-black/40 px-4 py-5">
                    <h2 className="font-serif text-2xl text-white">{title}</h2>
                    {subtitle && <p className="text-secondary-300 text-sm">{subtitle}</p>}
                </div>
            </header>

            <div className="flex flex-col gap-4 p-4">
                <dl className="grid grid-cols-1 gap-3 sm:grid-cols-2">
                    {details.map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-secondary-300 text-sm">{label}</dt>
                            <dd className="text-white">{value ?? "Not set"}</dd>
                        </div>
                    ))}
                </dl>

                <p id={usageId} className="text-secondary-300 text-sm">
                    {inUse
                        ? `Used by ${usage.join(", ")}. Remove or reassign these before deleting.`
                        : "Not used by any records yet."}
                </p>

                <div className="flex flex-wrap gap-2">
                    <Link
                        href={links.edit}
                        className={`border-ink-500 hover:bg-ink-600/30 inline-flex items-center gap-1.5 rounded border px-3 py-1.5 text-sm text-white ${focusRing}`}
                    >
                        <Icon icon="edit" style="light" />
                        Edit<span className="sr-only"> {title}</span>
                    </Link>
                    <Button
                        disabled={inUse}
                        aria-describedby={usageId}
                        onClick={onDelete}
                        className="data-disabled:border-secondary-600 data-disabled:text-secondary-400 inline-flex items-center gap-1.5 rounded border border-red-400 px-3 py-1.5 text-sm text-red-300 data-disabled:cursor-not-allowed data-focus:outline-2 data-focus:outline-offset-2 data-focus:outline-red-400 data-hover:bg-red-600/20"
                    >
                        <Icon icon="trash" style="light" />
                        Delete<span className="sr-only"> {title}</span>
                    </Button>
                </div>
            </div>
        </article>
    );
}
