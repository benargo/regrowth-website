import ReactMarkdown from "react-markdown";
import CopyButton from "@/Components/CopyButton";

/**
 * Pre-processes custom markdown syntax before passing to ReactMarkdown.
 * Handles:
 * - Wowhead links: !wh[text](item=12345) -> [text](https://www.wowhead.com/tbc/item=12345)
 * - Underline: __text__ -> <u>text</u>
 */
function preprocessMarkdown(text) {
    if (!text) return "";

    // Convert !wh[text](item=12345) to standard markdown links with Wowhead URLs
    let processed = text.replace(/!wh\[([^\]]+)\]\(([^)]+)\)/g, "[$1](https://www.wowhead.com/tbc/$2)");

    // Convert __underline__ to HTML <u> tags (will be handled by custom component)
    processed = processed.replace(/__([^_]+)__/g, "<u>$1</u>");

    return processed;
}

export default function FormattedMarkdown({ children, className = "" }) {
    if (!children) return null;

    const processedText = preprocessMarkdown(children);

    return (
        <div
            className={`prose prose-invert prose-p:my-2 prose-ul:my-2 prose-ol:my-2 prose-li:my-0 max-w-none ${className}`}
        >
            <ReactMarkdown
                allowedElements={["a", "code", "em", "h2", "h3", "li", "ol", "p", "pre", "strong", "u", "ul"]}
                unwrapDisallowed={true}
                components={{
                    a: ({ node, href, ...props }) => {
                        const isWowhead = href?.includes("wowhead.com");
                        return (
                            <a
                                {...props}
                                href={href}
                                className="text-amber-400 underline hover:text-amber-300"
                                target="_blank"
                                rel="noopener noreferrer"
                                data-wowhead={isWowhead ? href.split("/").pop() : undefined}
                            />
                        );
                    },
                    code: ({ node, inline, ...props }) =>
                        inline ? (
                            <code className="rounded bg-gray-900 px-1 py-0.5 font-mono text-sm" {...props} />
                        ) : (
                            <code className="font-mono" {...props} />
                        ),
                    h2: ({ node, ...props }) => (
                        <h2
                            className="mb-2 mt-4 text-base font-semibold uppercase tracking-wider text-amber-500/80"
                            {...props}
                        />
                    ),
                    h3: ({ node, ...props }) => (
                        <h3
                            className="mb-1 mt-2 text-sm font-semibold uppercase tracking-wider text-amber-500"
                            {...props}
                        />
                    ),
                    pre: ({ node, children, ...props }) => {
                        const codeText = node?.children?.[0]?.children?.[0]?.value ?? "";
                        return (
                            <div className="not-prose group/codeblock relative my-2">
                                <pre className="overflow-x-auto rounded-md bg-gray-900 p-3 text-sm" {...props}>
                                    {children}
                                </pre>
                                <div className="absolute right-2 top-2 opacity-0 transition-opacity group-hover/codeblock:opacity-100">
                                    <CopyButton
                                        getValue={() => codeText}
                                        className="p-1 text-gray-400 hover:text-white"
                                    />
                                </div>
                            </div>
                        );
                    },
                    u: ({ node, ...props }) => <span className="underline" {...props} />,
                }}
            >
                {processedText}
            </ReactMarkdown>
        </div>
    );
}
