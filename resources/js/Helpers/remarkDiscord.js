import { discordTagPattern } from "@/Helpers/discordTags";

const PLACEHOLDER_START = "";
const PLACEHOLDER_END = "";
const PLACEHOLDER_SOURCE = `${PLACEHOLDER_START}(\\d+)${PLACEHOLDER_END}`;
const SUBTEXT_PREFIX = "-# ";
const PARAGRAPH_CONTAINERS = new Set(["root", "blockquote", "listItem"]);

/**
 * Swaps each Discord tag in the markdown for a private-use placeholder token
 * (U+E000, its index, U+E001), returning the encoded markdown and the tags in
 * index order for `remarkDiscord` to map back.
 *
 * @param {string} markdown
 * @returns {{ markdown: string, tags: string[] }}
 */
export function encodeDiscordTags(markdown) {
    const tags = [];
    const encoded = (markdown ?? "").replace(discordTagPattern(), (raw) => {
        tags.push(raw);

        return `${PLACEHOLDER_START}${tags.length - 1}${PLACEHOLDER_END}`;
    });

    return { markdown: encoded, tags };
}

/**
 * Remark plugin, run after remark-gfm, for markdown encoded by `encodeDiscordTags`:
 * - placeholder tokens become `discord-mention` nodes (raw text inside code);
 * - `__text__` strong becomes `u`;
 * - each newline inside a paragraph becomes a line break;
 * - a line starting `-# ` becomes its own `small` subtext block.
 *
 * @param {{ tags?: string[] }} [options]
 */
export default function remarkDiscord({ tags = [] } = {}) {
    return (tree, file) => {
        const source = String(file);

        markUnderlines(tree, source);
        restoreTags(tree, tags);
        convertParagraphNewlines(tree);
        splitSubtext(tree);
    };
}

/**
 * Calls the callback for the node and each of its descendants, parents first.
 */
function walk(node, callback) {
    callback(node);

    node.children?.forEach((child) => walk(child, callback));
}

/**
 * Renders `strong` nodes written with `__` as `u` elements.
 */
function markUnderlines(tree, source) {
    walk(tree, (node) => {
        const start = node.position?.start?.offset;

        if (node.type === "strong" && start !== undefined && source.slice(start, start + 2) === "__") {
            node.data = { ...node.data, hName: "u" };
        }
    });
}

/**
 * The text with every placeholder token replaced by its original tag.
 */
function decodePlaceholders(value, tags) {
    return value.replace(new RegExp(PLACEHOLDER_SOURCE, "g"), (token, index) => tags[Number(index)] ?? token);
}

/**
 * Splits text nodes around placeholder tokens into text and `discord-mention`
 * nodes, and puts the raw tags back into code, URLs, titles and alt text.
 */
function restoreTags(tree, tags) {
    walk(tree, (node) => {
        if (node.type === "inlineCode" || node.type === "code") {
            node.value = decodePlaceholders(node.value, tags);
        }

        ["url", "title", "alt"].forEach((key) => {
            if (typeof node[key] === "string") {
                node[key] = decodePlaceholders(node[key], tags);
            }
        });

        if (node.children) {
            node.children = node.children.flatMap((child) =>
                child.type === "text" ? splitMentions(child.value, tags) : [child],
            );
        }
    });
}

/**
 * Text and `discord-mention` nodes for a text value containing placeholder tokens.
 */
function splitMentions(value, tags) {
    const nodes = [];
    const pattern = new RegExp(PLACEHOLDER_SOURCE, "g");
    let lastIndex = 0;

    for (const match of value.matchAll(pattern)) {
        const raw = tags[Number(match[1])];

        if (raw === undefined) {
            continue;
        }

        if (match.index > lastIndex) {
            nodes.push({ type: "text", value: value.slice(lastIndex, match.index) });
        }

        nodes.push({ type: "discordMention", data: { hName: "discord-mention", hProperties: { raw } } });
        lastIndex = match.index + match[0].length;
    }

    if (lastIndex < value.length) {
        nodes.push({ type: "text", value: value.slice(lastIndex) });
    }

    return nodes;
}

/**
 * Splits every text node inside a paragraph at its newlines, with a `break` between the parts.
 */
function convertParagraphNewlines(tree) {
    walk(tree, (node) => {
        if (node.type === "paragraph") {
            splitNewlines(node);
        }
    });
}

function splitNewlines(parent) {
    parent.children = parent.children.flatMap((child) => {
        if (child.type !== "text") {
            if (child.children) {
                splitNewlines(child);
            }

            return [child];
        }

        return child.value
            .split("\n")
            .flatMap((line, index) => [
                ...(index > 0 ? [{ type: "break" }] : []),
                ...(line === "" ? [] : [{ type: "text", value: line }]),
            ]);
    });
}

/**
 * Replaces each paragraph holding `-# ` lines with paragraphs for its plain
 * lines and a `subtext` (`small`) block for each `-# ` line.
 */
function splitSubtext(tree) {
    walk(tree, (node) => {
        if (PARAGRAPH_CONTAINERS.has(node.type) && node.children) {
            node.children = node.children.flatMap((child) =>
                child.type === "paragraph" ? paragraphBlocks(child) : [child],
            );
        }
    });
}

/**
 * The paragraph's lines regrouped into paragraph and `subtext` blocks.
 */
function paragraphBlocks(paragraph) {
    const lines = [[]];

    paragraph.children.forEach((child) => {
        if (child.type === "break") {
            lines.push([]);
        } else {
            lines.at(-1).push(child);
        }
    });

    if (!lines.some(isSubtextLine)) {
        return [paragraph];
    }

    const blocks = [];
    let plainLines = [];

    const flushPlainLines = () => {
        if (plainLines.length > 0) {
            blocks.push({
                type: "paragraph",
                children: plainLines.flatMap((line, index) => (index > 0 ? [{ type: "break" }, ...line] : line)),
            });
            plainLines = [];
        }
    };

    lines.forEach((line) => {
        if (!isSubtextLine(line)) {
            plainLines.push(line);

            return;
        }

        flushPlainLines();
        blocks.push({ type: "subtext", data: { hName: "small" }, children: withoutSubtextPrefix(line) });
    });

    flushPlainLines();

    return blocks;
}

function isSubtextLine(line) {
    return line[0]?.type === "text" && line[0].value.startsWith(SUBTEXT_PREFIX);
}

function withoutSubtextPrefix([first, ...rest]) {
    const value = first.value.slice(SUBTEXT_PREFIX.length);

    return value === "" ? rest : [{ ...first, value }, ...rest];
}
