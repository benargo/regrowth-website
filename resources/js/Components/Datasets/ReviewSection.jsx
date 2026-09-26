import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import { linkButtonClassName } from "@/Components/FormControls";

/**
 * The options a relationship group ({ options, selected_ids }) links to the
 * record, in option order.
 */
export function selectedOptions(group) {
    return group.options.filter((option) => group.selected_ids.includes(option.id));
}

/**
 * One step's summary on a dataset's setup review page, with a link back to
 * edit that step.
 */
export default function ReviewSection({ id, title, editHref, editLabel, children }) {
    return (
        <section aria-labelledby={`${id}-heading`} className="border-ink-600/40 flex flex-col gap-4 border-t pt-6">
            <header className="flex flex-wrap items-center justify-between gap-4">
                <h2 id={`${id}-heading`} className="font-serif text-2xl text-white">
                    {title}
                </h2>
                <Link href={editHref} className={linkButtonClassName} aria-label={editLabel}>
                    <Icon icon="pen" style="light" />
                    Edit
                </Link>
            </header>
            {children}
        </section>
    );
}

/**
 * A list of [label, value] pairs. Blank values read "Not set".
 */
function Details({ details }) {
    return (
        <dl className="grid gap-x-8 gap-y-3 text-sm sm:grid-cols-[max-content_1fr]">
            {details.map(([label, value]) => (
                <div key={label} className="contents">
                    <dt className="text-secondary-300">{label}</dt>
                    <dd className={value ? "text-white" : "text-secondary-400 italic"}>{value || "Not set"}</dd>
                </div>
            ))}
        </dl>
    );
}

/**
 * Linked records under an optional heading. Each item is { id, primary,
 * secondary?, icon? }, where icon is an image URL.
 */
function Records({ heading, items }) {
    return (
        <div className="flex flex-col gap-2">
            {heading && <h3 className="text-secondary-300 text-sm font-semibold">{heading}</h3>}
            {items.length === 0 ? (
                <p className="text-secondary-400 text-sm italic">None linked.</p>
            ) : (
                <ul className="grid gap-x-8 gap-y-2 text-sm sm:grid-cols-2">
                    {items.map((item) => (
                        <li key={item.id} className="flex items-center gap-2">
                            {item.icon && <img src={item.icon} alt="" className="h-4 w-4 rounded-xs" />}
                            <div className="flex flex-col">
                                <span className="text-white">{item.primary}</span>
                                {item.secondary && <span className="text-secondary-400">{item.secondary}</span>}
                            </div>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

ReviewSection.Details = Details;
ReviewSection.Records = Records;
