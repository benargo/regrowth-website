import CopyButton from "@/Components/CopyButton";

/**
 * React-markdown component renderers shared by the site's markdown views:
 * external links (tagged for Wowhead tooltips), blockquotes, inline and
 * fenced code with a copy button, `h2`/`h3` headings and underline.
 */
export const markdownRenderers = {
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
    blockquote: ({ node, ...props }) => (
        <blockquote className="my-2 border-l-4 border-amber-500/50 pl-4 italic text-gray-300" {...props} />
    ),
    code: ({ node, ...props }) => <code className="font-mono" {...props} />,
    h2: ({ node, ...props }) => (
        <h2 className="mb-2 mt-4 text-base font-semibold uppercase tracking-wider text-amber-500/80" {...props} />
    ),
    h3: ({ node, ...props }) => (
        <h3 className="mb-1 mt-2 text-sm font-semibold uppercase tracking-wider text-amber-500" {...props} />
    ),
    pre: ({ node, children, ...props }) => {
        const codeText = node?.children?.[0]?.children?.[0]?.value ?? "";

        return (
            <div className="not-prose group/codeblock relative my-2">
                <pre className="overflow-x-auto rounded-md bg-gray-900 p-3 text-sm" {...props}>
                    {children}
                </pre>
                <div className="absolute right-2 top-2 opacity-0 transition-opacity group-hover/codeblock:opacity-100">
                    <CopyButton getValue={() => codeText} className="p-1 text-gray-400 hover:text-white" />
                </div>
            </div>
        );
    },
    u: ({ node, ...props }) => <span className="underline" {...props} />,
};
