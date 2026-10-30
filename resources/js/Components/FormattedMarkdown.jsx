import ReactMarkdown from "react-markdown";
import { markdownRenderers } from "@/Helpers/markdownRenderers";

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
                allowedElements={[
                    "a",
                    "blockquote",
                    "code",
                    "em",
                    "h2",
                    "h3",
                    "li",
                    "ol",
                    "p",
                    "pre",
                    "strong",
                    "u",
                    "ul",
                ]}
                unwrapDisallowed={true}
                components={markdownRenderers}
            >
                {processedText}
            </ReactMarkdown>
        </div>
    );
}
