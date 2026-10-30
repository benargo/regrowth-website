import { StateEffect } from "@codemirror/state";
import { Decoration, EditorView, MatchDecorator, ViewPlugin, WidgetType } from "@codemirror/view";
import { discordTagPattern, parseDiscordTag } from "@/Helpers/discordTags";

/**
 * An effect that rebuilds every chip from the current mention lookup.
 */
export const refreshMentionChips = StateEffect.define();

/**
 * The text, tooltip, image and state a Discord tag resolves to in the lookup.
 *
 * @param {string} raw
 * @param {{ channels: Map, roles: Map, emoji: Map }} lookup
 * @returns {{ label: string, title: string|null, imageUrl: string|null, isMissing: boolean }}
 */
function resolveChip(raw, lookup) {
    const tag = parseDiscordTag(raw);

    if (tag.kind === "channel") {
        const channel = lookup.channels.get(tag.id);

        if (channel === undefined) {
            return { label: "#unknown-channel", title: "Unknown channel", imageUrl: null, isMissing: true };
        }

        if (channel.is_deleted) {
            return { label: `#${channel.name}`, title: "Deleted channel", imageUrl: null, isMissing: true };
        }

        return { label: `#${channel.name}`, title: null, imageUrl: null, isMissing: false };
    }

    if (tag.kind === "role") {
        const role = lookup.roles.get(tag.id);

        if (role === undefined) {
            return { label: "@unknown-role", title: "Unknown role", imageUrl: null, isMissing: true };
        }

        return { label: `@${role.name}`, title: null, imageUrl: null, isMissing: false };
    }

    const emoji = lookup.emoji.get(tag.id);

    if (emoji === undefined) {
        return { label: `:${tag.name}:`, title: "Unknown emoji", imageUrl: null, isMissing: true };
    }

    return { label: `:${emoji.name}:`, title: `:${emoji.name}:`, imageUrl: emoji.url, isMissing: false };
}

/**
 * A Discord tag drawn as a channel or role pill, an emoji image, or a red
 * pill with a tooltip when its target is unknown or deleted.
 */
class MentionChipWidget extends WidgetType {
    constructor(raw, lookup) {
        super();
        this.raw = raw;
        this.chip = resolveChip(raw, lookup);
    }

    eq(other) {
        return (
            other.raw === this.raw &&
            other.chip.label === this.chip.label &&
            other.chip.title === this.chip.title &&
            other.chip.imageUrl === this.chip.imageUrl &&
            other.chip.isMissing === this.chip.isMissing
        );
    }

    toDOM() {
        if (this.chip.imageUrl !== null) {
            const image = document.createElement("img");
            image.src = this.chip.imageUrl;
            image.alt = this.chip.label;
            image.title = this.chip.title;
            image.className = "cm-discord-emoji";

            return image;
        }

        const pill = document.createElement("span");
        pill.textContent = this.chip.label;
        pill.className = this.chip.isMissing ? "cm-discord-chip cm-discord-chip-missing" : "cm-discord-chip";

        if (this.chip.title !== null) {
            pill.title = this.chip.title;
        }

        return pill;
    }
}

const CHIP_THEME = EditorView.baseTheme({
    ".cm-discord-chip": {
        borderRadius: "0.25rem",
        padding: "0 0.25rem",
        fontWeight: "500",
        backgroundColor: "rgb(99 102 241 / 0.25)",
        color: "#c7d2fe",
    },
    ".cm-discord-chip-missing": {
        backgroundColor: "rgb(239 68 68 / 0.2)",
        color: "#fca5a5",
        textDecoration: "underline dotted",
    },
    ".cm-discord-emoji": {
        display: "inline-block",
        width: "1.375em",
        height: "1.375em",
        objectFit: "contain",
        verticalAlign: "-0.3em",
    },
});

/**
 * Draws every Discord tag in the document as a chip resolved through
 * `lookupRef.current` (a `buildMentionLookup` result). The cursor steps over
 * a chip and Backspace/Delete remove it whole. Dispatch `refreshMentionChips`
 * after the lookup changes to redraw them.
 *
 * @param {{ current: { channels: Map, roles: Map, emoji: Map } }} lookupRef
 * @returns {import("@codemirror/state").Extension}
 */
export function mentionChips(lookupRef) {
    const decorator = new MatchDecorator({
        regexp: discordTagPattern(),
        decoration: (match) => Decoration.replace({ widget: new MentionChipWidget(match[0], lookupRef.current) }),
    });

    const plugin = ViewPlugin.fromClass(
        class {
            constructor(view) {
                this.chips = decorator.createDeco(view);
            }

            update(update) {
                const isRefresh = update.transactions.some((transaction) =>
                    transaction.effects.some((effect) => effect.is(refreshMentionChips)),
                );

                this.chips = isRefresh ? decorator.createDeco(update.view) : decorator.updateDeco(update, this.chips);
            }
        },
        {
            decorations: (instance) => instance.chips,
            provide: (chipPlugin) =>
                EditorView.atomicRanges.of((view) => view.plugin(chipPlugin)?.chips ?? Decoration.none),
        },
    );

    return [plugin, CHIP_THEME];
}
