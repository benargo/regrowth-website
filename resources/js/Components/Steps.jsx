/**
 * An ordered list of instructions, drawn as numbered markers joined by a
 * vertical rail. Steps are numbered automatically with a CSS counter, so
 * they can be added, removed, or rendered conditionally without renumbering.
 */
export default function Steps({ children, className = "" }) {
    return <ol className={`list-none [counter-reset:step] ${className}`}>{children}</ol>;
}

/**
 * A single step within `<Steps>`: a numbered marker, a title, and its content.
 */
export function Step({ title, children }) {
    return (
        <li className="group relative pb-8 pl-12 [counter-increment:step] last:pb-0">
            <span aria-hidden="true" className="bg-ink-700 absolute top-9 bottom-1 left-4 w-px group-last:hidden" />
            <span
                aria-hidden="true"
                className="border-ink-600 bg-ground-800 text-heading absolute top-0 left-0 flex size-8 items-center justify-center rounded-full border text-sm font-bold before:content-[counter(step)]"
            />
            <h3 className="text-heading mb-2 pt-0.5 font-serif text-lg">{title}</h3>
            <div className="text-secondary-200 space-y-3">{children}</div>
        </li>
    );
}
