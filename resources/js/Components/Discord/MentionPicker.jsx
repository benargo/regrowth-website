import { useMemo, useState } from "react";
import {
    Combobox,
    ComboboxInput,
    ComboboxOption,
    ComboboxOptions,
    Popover,
    PopoverButton,
    PopoverPanel,
} from "@headlessui/react";
import Icon from "@/Components/FontAwesome/Icon";
import { controlClassName } from "@/Components/FormControls";

const NO_CATEGORY = "No category";

const PICKER_KINDS = {
    channel: {
        icon: "hashtag",
        title: "Channel",
        placeholder: "Search channels",
        noun: "channels",
        tag: (channel) => `<#${channel.id}>`,
        options: (mentions) =>
            (mentions?.channels ?? []).filter((channel) => !channel.is_deleted && !channel.is_category),
    },
    role: {
        icon: "at",
        title: "Role",
        placeholder: "Search roles",
        noun: "roles",
        tag: (role) => `<@&${role.id}>`,
        options: (mentions) => mentions?.roles ?? [],
    },
    emoji: {
        icon: "smile",
        title: "Emoji",
        placeholder: "Search emoji",
        noun: "emoji",
        tag: (emoji) => `<${emoji.animated ? "a" : ""}:${emoji.name}:${emoji.id}>`,
        options: (mentions) => (mentions?.emoji ?? []).filter((emoji) => emoji.available !== false),
    },
};

/**
 * Channels split into their categories, in the order each category first
 * appears, with uncategorised channels last under "No category".
 *
 * @param {Array<{ id: string, name: string, category: string|null }>} channels
 * @returns {Array<{ category: string, channels: Array<object> }>}
 */
function groupByCategory(channels) {
    const groups = new Map();

    for (const channel of channels) {
        const category = channel.category ?? NO_CATEGORY;

        if (!groups.has(category)) {
            groups.set(category, []);
        }

        groups.get(category).push(channel);
    }

    const ordered = [...groups.entries()].filter(([category]) => category !== NO_CATEGORY);

    if (groups.has(NO_CATEGORY)) {
        ordered.push([NO_CATEGORY, groups.get(NO_CATEGORY)]);
    }

    return ordered.map(([category, groupChannels]) => ({ category, channels: groupChannels }));
}

/**
 * A channel or role option: its name after a muted `prefix` sign.
 */
function NamedOption({ option, prefix }) {
    return (
        <ComboboxOption
            value={option}
            className="data-focus:bg-brown-700 flex cursor-pointer items-center gap-1.5 rounded px-2 py-1 text-sm text-white"
        >
            <span className="text-gray-400" aria-hidden="true">
                {prefix}
            </span>
            <span className="truncate">{option.name}</span>
        </ComboboxOption>
    );
}

/**
 * A searchable list of the channels, roles or emoji in the mention
 * directory, closing and passing the picked one's Discord tag to `onPick`.
 */
function PickerSearch({ kind, mentions, onPick, close }) {
    const [query, setQuery] = useState("");
    const config = PICKER_KINDS[kind];

    const matches = useMemo(() => {
        const needle = query.trim().toLowerCase();
        const options = config.options(mentions);

        return needle === "" ? options : options.filter((option) => option.name.toLowerCase().includes(needle));
    }, [config, mentions, query]);

    function handlePick(option) {
        if (option === null) {
            return;
        }

        close();
        onPick(config.tag(option));
    }

    return (
        <Combobox value={null} onChange={handlePick}>
            <div className="border-b border-amber-600 p-2">
                <ComboboxInput
                    autoFocus
                    aria-label={config.placeholder}
                    placeholder={config.placeholder}
                    autoComplete="off"
                    spellCheck={false}
                    onChange={(event) => setQuery(event.target.value)}
                    className={`${controlClassName} px-3 py-1.5 text-sm`}
                />
            </div>

            <ComboboxOptions
                static
                className={`max-h-64 overflow-y-auto p-1 empty:hidden ${kind === "emoji" ? "grid grid-cols-8 gap-0.5" : ""}`}
            >
                {kind === "channel" &&
                    groupByCategory(matches).map(({ category, channels }) => (
                        <div key={category} role="presentation">
                            <div className="px-2 pt-2 pb-1 text-xs font-semibold text-amber-200/80">{category}</div>
                            {channels.map((channel) => (
                                <NamedOption key={channel.id} option={channel} prefix="#" />
                            ))}
                        </div>
                    ))}

                {kind === "role" && matches.map((role) => <NamedOption key={role.id} option={role} prefix="@" />)}

                {kind === "emoji" &&
                    matches.map((emoji) => (
                        <ComboboxOption
                            key={emoji.id}
                            value={emoji}
                            title={`:${emoji.name}:`}
                            aria-label={`:${emoji.name}:`}
                            className="data-focus:bg-brown-700 flex aspect-square cursor-pointer items-center justify-center rounded p-1 data-focus:ring-1 data-focus:ring-amber-400"
                        >
                            <img src={emoji.url} alt="" loading="lazy" className="size-6 object-contain" />
                        </ComboboxOption>
                    ))}
            </ComboboxOptions>

            {matches.length === 0 && (
                <p className="px-3 py-2 text-sm text-gray-300">
                    {query.trim() === "" ? `No ${config.noun} to pick from.` : `No ${config.noun} match your search.`}
                </p>
            )}
        </Combobox>
    );
}

/**
 * A toolbar button that opens a searchable picker of the mention directory's
 * channels, roles or emoji and passes the picked one's Discord tag to
 * `onPick`. Channels leave out deleted channels and categories, grouped by
 * category; emoji leave out unavailable ones and show as an image grid.
 *
 * @param {{ kind: "emoji"|"channel"|"role", mentions: { channels: Array, roles: Array, emoji: Array }, onPick: (tag: string) => void }} props
 */
export default function MentionPicker({ kind, mentions, onPick }) {
    const config = PICKER_KINDS[kind];

    return (
        <Popover className="group relative">
            <PopoverButton
                aria-label={config.title}
                className="bg-brown-700 focus:ring-primary flex h-8 w-8 items-center justify-center rounded border border-amber-500 px-2 py-1 text-sm text-white focus:ring-1 focus:outline-hidden data-open:border-amber-300"
            >
                <Icon icon={config.icon} style="solid" />
            </PopoverButton>
            <div className="pointer-events-none absolute top-full left-1/2 z-10 mt-1.5 -translate-x-1/2 rounded bg-gray-900 px-2 py-1 text-xs whitespace-nowrap text-white opacity-0 shadow-lg transition-opacity duration-150 group-hover:opacity-100 group-data-open:hidden">
                <div className="absolute -top-1 left-1/2 -translate-x-1/2 border-4 border-transparent border-b-gray-900"></div>
                {config.title}
            </div>

            <PopoverPanel
                anchor="bottom start"
                className={`bg-brown-800 z-50 rounded border border-amber-600 shadow-lg shadow-black/40 [--anchor-gap:4px] ${kind === "emoji" ? "w-80" : "w-72"}`}
            >
                {({ close }) => <PickerSearch kind={kind} mentions={mentions} onPick={onPick} close={close} />}
            </PopoverPanel>
        </Popover>
    );
}
