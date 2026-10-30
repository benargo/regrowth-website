/**
 * The most characters Discord accepts in a single message.
 */
export const DISCORD_MESSAGE_LIMIT = 2000;

const DISCORD_TAG_SOURCE = "<#(\\d+)>|<@&(\\d+)>|<(a?):(\\w+):(\\d+)>";

/**
 * A new global regex matching Discord channel (`<#id>`), role (`<@&id>`) and
 * custom emoji (`<:name:id>`, `<a:name:id>`) tags.
 */
export function discordTagPattern() {
    return new RegExp(DISCORD_TAG_SOURCE, "g");
}

/**
 * The parts of a single Discord tag, or null when the string isn't one.
 *
 * @param {string} raw
 * @returns {{ kind: "channel"|"role"|"emoji", id: string, name?: string, animated?: boolean } | null}
 */
export function parseDiscordTag(raw) {
    const match = new RegExp(`^(?:${DISCORD_TAG_SOURCE})$`).exec(raw ?? "");

    if (match === null) {
        return null;
    }

    const [, channelId, roleId, animatedFlag, emojiName, emojiId] = match;

    if (channelId !== undefined) {
        return { kind: "channel", id: channelId };
    }

    if (roleId !== undefined) {
        return { kind: "role", id: roleId };
    }

    return { kind: "emoji", id: emojiId, name: emojiName, animated: animatedFlag === "a" };
}

/**
 * Maps of the mention directory's channels, roles and emoji keyed by ID.
 *
 * @param {{ channels?: Array<{ id: string }>, roles?: Array<{ id: string }>, emoji?: Array<{ id: string }> } | null | undefined} mentions
 * @returns {{ channels: Map<string, object>, roles: Map<string, object>, emoji: Map<string, object> }}
 */
export function buildMentionLookup(mentions) {
    const byId = (items) => new Map((items ?? []).map((item) => [item.id, item]));

    return {
        channels: byId(mentions?.channels),
        roles: byId(mentions?.roles),
        emoji: byId(mentions?.emoji),
    };
}
