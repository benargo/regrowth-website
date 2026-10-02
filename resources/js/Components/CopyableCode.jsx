import CopyButton from "@/Components/CopyButton";

/**
 * An inline code snippet, such as a slash command, with a button that copies
 * it to the clipboard.
 */
export default function CopyableCode({ children, value = children }) {
    return (
        <span className="inline-flex items-center gap-1 whitespace-nowrap">
            <code className="border-ink-800 bg-ground-800 rounded-xs border px-1.5 py-0.5 font-mono font-bold text-white">
                {children}
            </code>
            <CopyButton
                as="span"
                getValue={value}
                className="text-secondary-400 focus-visible:outline-ink-400 rounded-xs px-1 hover:text-white focus-visible:outline-2"
            />
        </span>
    );
}
