import { parseDiscordTag } from "@/Helpers/discordTags";

const PILL = "rounded bg-indigo-500/25 px-1 font-medium text-indigo-200 not-italic";
const MUTED = "text-gray-500";

/**
 * A Discord tag as Discord shows it: a `#channel` pill linking to the channel
 * on discord.com, an `@role` pill, a custom
 * emoji image, or muted text for a deleted channel or an unknown target.
 * `lookup` is the result of `buildMentionLookup`.
 *
 * @param {{ raw: string, lookup: { channels: Map, roles: Map, emoji: Map } }} props
 */
export default function DiscordMention({ raw, lookup }) {
    const tag = parseDiscordTag(raw);

    if (tag === null) {
        return raw;
    }

    if (tag.kind === "channel") {
        const channel = lookup.channels.get(tag.id);

        if (channel === undefined) {
            return (
                <span className={MUTED} title="Unknown channel">
                    #unknown-channel
                </span>
            );
        }

        if (channel.is_deleted) {
            return (
                <span className={MUTED} title="Deleted channel">
                    #{channel.name}
                </span>
            );
        }

        return (
            <a
                href={channel.url}
                target="_blank"
                rel="noopener noreferrer"
                className={`${PILL} no-underline hover:bg-indigo-500/40 hover:text-white`}
            >
                #{channel.name}
            </a>
        );
    }

    if (tag.kind === "role") {
        const role = lookup.roles.get(tag.id);

        if (role === undefined) {
            return (
                <span className={MUTED} title="Unknown role">
                    @unknown-role
                </span>
            );
        }

        return <span className={PILL}>@{role.name}</span>;
    }

    const emoji = lookup.emoji.get(tag.id);

    if (emoji === undefined) {
        return `:${tag.name}:`;
    }

    return (
        <img
            src={emoji.url}
            alt={`:${tag.name}:`}
            title={`:${emoji.name}:`}
            className="inline-block h-[1.375em] w-[1.375em] object-contain align-[-0.3em]"
            loading="lazy"
        />
    );
}
