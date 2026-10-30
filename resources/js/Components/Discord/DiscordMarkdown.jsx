import { useMemo } from "react";
import ReactMarkdown from "react-markdown";
import remarkGfm from "remark-gfm";
import DiscordMention from "@/Components/Discord/DiscordMention";
import { buildMentionLookup } from "@/Helpers/discordTags";
import { markdownRenderers } from "@/Helpers/markdownRenderers";
import remarkDiscord, { encodeDiscordTags } from "@/Helpers/remarkDiscord";

const LEADING_HEADING_PATTERN = /^#+ [^\n]*\n?/;

const ALLOWED_ELEMENTS = [
    "a",
    "blockquote",
    "br",
    "code",
    "del",
    "discord-mention",
    "em",
    "h1",
    "h2",
    "h3",
    "li",
    "ol",
    "p",
    "pre",
    "small",
    "strong",
    "u",
    "ul",
];

const DISCORD_RENDERERS = {
    h1: ({ node, ...props }) => <h2 className="mt-6 mb-2 font-serif text-2xl text-amber-200" {...props} />,
    li: ({ node, ...props }) => <li className="pl-1" {...props} />,
    ol: ({ node, ...props }) => <ol className="my-2 list-decimal space-y-1 pl-6" {...props} />,
    p: ({ node, ...props }) => <p className="my-3" {...props} />,
    small: ({ node, ...props }) => <small className="my-1 block text-xs text-gray-400" {...props} />,
    ul: ({ node, ...props }) => <ul className="my-2 list-disc space-y-1 pl-6" {...props} />,
};

/**
 * Discord-flavoured markdown: channel, role and emoji tags resolved against
 * `mentions` (the mention directory), `__underline__`, `~~strike~~`, bare-URL
 * links, a line break for every newline and `-# ` subtext. Raw HTML is never
 * rendered. `skipFirstHeading` leaves out the content's leading heading line.
 *
 * @param {{ content: string, mentions: object, skipFirstHeading?: boolean, className?: string }} props
 */
export default function DiscordMarkdown({ content, mentions, skipFirstHeading = false, className = "" }) {
    const lookup = useMemo(() => buildMentionLookup(mentions), [mentions]);
    const { markdown, tags } = useMemo(
        () => encodeDiscordTags(skipFirstHeading ? (content ?? "").replace(LEADING_HEADING_PATTERN, "") : content),
        [content, skipFirstHeading],
    );
    const components = useMemo(
        () => ({
            ...markdownRenderers,
            ...DISCORD_RENDERERS,
            "discord-mention": ({ raw }) => <DiscordMention raw={raw} lookup={lookup} />,
        }),
        [lookup],
    );

    return (
        <div className={`leading-relaxed text-gray-200 ${className}`}>
            <ReactMarkdown
                allowedElements={ALLOWED_ELEMENTS}
                unwrapDisallowed={true}
                remarkPlugins={[[remarkGfm, { singleTilde: false }], [remarkDiscord, { tags }]]}
                components={components}
            >
                {markdown}
            </ReactMarkdown>
        </div>
    );
}
