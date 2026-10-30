import DiscordMention from "@/Components/Discord/DiscordMention";
import { parseDiscordTag } from "@/Helpers/discordTags";

/**
 * A raid handbook chapter's emoji (unicode, or a custom Discord emoji) and
 * title, rendered as the `as` element. `lookup` is the result of `buildMentionLookup`.
 *
 * @param {{ chapter: { title: string, emoji: string|null }, lookup: object, as?: string, className?: string }} props
 */
export default function ChapterTitle({ chapter, lookup, as: Element = "span", className = "" }) {
    const isCustomEmoji = parseDiscordTag(chapter.emoji) !== null;

    return (
        <Element className={`inline-flex items-center gap-2 ${className}`}>
            {chapter.emoji && (
                <span aria-hidden="true" className="shrink-0">
                    {isCustomEmoji ? <DiscordMention raw={chapter.emoji} lookup={lookup} /> : chapter.emoji}
                </span>
            )}
            <span>{chapter.title}</span>
        </Element>
    );
}
