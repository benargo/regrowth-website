import { useEffect, useId, useRef, useState } from "react";
import { Compartment, EditorState } from "@codemirror/state";
import { EditorView, drawSelection, keymap } from "@codemirror/view";
import { defaultKeymap, history, historyKeymap } from "@codemirror/commands";
import { markdown } from "@codemirror/lang-markdown";
import FormatButton from "@/Components/FormatButton";
import { ALL_FORMATS } from "@/Components/MarkdownEditor";
import MentionPicker from "@/Components/Discord/MentionPicker";
import {
    insertLink,
    insertText,
    toggleBulletList,
    toggleHeading,
    wrapSelection,
} from "@/Components/Discord/Editor/formatting";
import { consumeEmittedValue } from "@/Components/Discord/Editor/emittedValues";
import { externalSync, lengthLimit } from "@/Components/Discord/Editor/lengthLimit";
import { mentionChips, refreshMentionChips } from "@/Components/Discord/Editor/mentionChips";
import { buildMentionLookup, DISCORD_MESSAGE_LIMIT } from "@/Helpers/discordTags";

const WARNING_LENGTH = 1900;

const MAX_PENDING_EMISSIONS = 100;

const FORMAT_GROUPS = [
    [
        { key: "heading2", command: toggleHeading(2) },
        { key: "heading3", command: toggleHeading(3) },
    ],
    [
        { key: "bold", command: wrapSelection("**") },
        { key: "italic", command: wrapSelection("*") },
        { key: "underline", command: wrapSelection("__") },
    ],
    [
        { key: "bulletList", command: toggleBulletList },
        { key: "link", command: insertLink },
    ],
];

const MENTION_KINDS = ["emoji", "channel", "role"];

const EDITOR_THEME = EditorView.theme(
    {
        "&": {
            backgroundColor: "#4a3d32",
            color: "#ffffff",
        },
        "&.cm-focused": {
            outline: "none",
        },
        ".cm-scroller": {
            fontFamily: "inherit",
            lineHeight: "1.6",
        },
        ".cm-content": {
            minHeight: "16rem",
            padding: "0.75rem 0",
            caretColor: "#fcd34d",
        },
        ".cm-line": {
            padding: "0 0.75rem",
        },
        ".cm-cursor, .cm-dropCursor": {
            borderLeftColor: "#fcd34d",
        },
        "&.cm-focused > .cm-scroller > .cm-selectionLayer .cm-selectionBackground, .cm-selectionBackground, .cm-content ::selection":
            {
                backgroundColor: "rgb(245 158 11 / 0.3)",
            },
    },
    { dark: true },
);

/**
 * The Tailwind text colour for the character counter at `length`.
 *
 * @param {number} length
 * @returns {string}
 */
function counterColour(length) {
    if (length >= DISCORD_MESSAGE_LIMIT) {
        return "text-red-400";
    }

    if (length >= WARNING_LENGTH) {
        return "text-amber-300";
    }

    return "text-gray-400";
}

/**
 * A CodeMirror editor for Discord markdown with a formatting toolbar, channel,
 * role and emoji pickers, tags drawn as chips, and a character counter. The
 * document is held to 2,000 characters; `onTrim` receives the number of
 * characters cut from a paste, or 0 when a change was rejected outright.
 * A `value` that differs from the document replaces it without calling
 * `onChange`.
 *
 * @param {Object} props
 * @param {string} props.value - The Discord markdown to show
 * @param {(value: string) => void} props.onChange - Receives the document after each edit
 * @param {{ channels: Array, roles: Array, emoji: Array }} props.mentions - The mention directory
 * @param {(cutCount: number) => void} [props.onTrim] - Receives the characters cut when the limit is reached
 * @param {string} props.label - The editor's accessible name
 */
export default function DiscordMarkdownEditor({ value, onChange, mentions, onTrim, label }) {
    const counterId = useId();
    const hostRef = useRef(null);
    const viewRef = useRef(null);
    const onChangeRef = useRef(onChange);
    const onTrimRef = useRef(onTrim);
    const lookupRef = useRef(buildMentionLookup(mentions));
    const attributesRef = useRef(new Compartment());
    const pendingEmissionsRef = useRef([]);
    const [documentLength, setDocumentLength] = useState(value.length);

    useEffect(() => {
        onChangeRef.current = onChange;
        onTrimRef.current = onTrim;
    });

    useEffect(() => {
        const view = new EditorView({
            parent: hostRef.current,
            state: EditorState.create({
                doc: value,
                extensions: [
                    history(),
                    drawSelection(),
                    keymap.of([...defaultKeymap, ...historyKeymap]),
                    markdown(),
                    EditorView.lineWrapping,
                    EDITOR_THEME,
                    attributesRef.current.of(
                        EditorView.contentAttributes.of({ "aria-label": label, "aria-describedby": counterId }),
                    ),
                    mentionChips(lookupRef),
                    lengthLimit({
                        limit: DISCORD_MESSAGE_LIMIT,
                        onTrim: (cutCount) => onTrimRef.current?.(cutCount),
                    }),
                    EditorView.updateListener.of((update) => {
                        if (!update.docChanged) {
                            return;
                        }

                        setDocumentLength(update.state.doc.length);

                        const isUserEdit = update.transactions.some(
                            (transaction) => transaction.docChanged && !transaction.annotation(externalSync),
                        );

                        if (isUserEdit) {
                            const emittedDocument = update.state.doc.toString();

                            pendingEmissionsRef.current.push(emittedDocument);
                            pendingEmissionsRef.current.splice(
                                0,
                                pendingEmissionsRef.current.length - MAX_PENDING_EMISSIONS,
                            );
                            onChangeRef.current?.(emittedDocument);
                        }
                    }),
                ],
            }),
        });

        viewRef.current = view;
        setDocumentLength(view.state.doc.length);

        return () => {
            view.destroy();
            viewRef.current = null;
        };
    }, []);

    useEffect(() => {
        const view = viewRef.current;

        if (view === null) {
            return;
        }

        if (consumeEmittedValue(pendingEmissionsRef.current, value)) {
            return;
        }

        const currentDocument = view.state.doc.toString();

        if (value === currentDocument) {
            return;
        }

        view.dispatch({
            changes: { from: 0, to: currentDocument.length, insert: value },
            annotations: externalSync.of(true),
        });
        pendingEmissionsRef.current = [];
    }, [value]);

    useEffect(() => {
        lookupRef.current = buildMentionLookup(mentions);
        viewRef.current?.dispatch({ effects: refreshMentionChips.of(null) });
    }, [mentions]);

    useEffect(() => {
        viewRef.current?.dispatch({
            effects: attributesRef.current.reconfigure(
                EditorView.contentAttributes.of({ "aria-label": label, "aria-describedby": counterId }),
            ),
        });
    }, [label, counterId]);

    function runCommand(command) {
        if (viewRef.current !== null) {
            command(viewRef.current);
        }
    }

    return (
        <div>
            <div className="focus-within:border-primary focus-within:ring-primary overflow-hidden rounded-md border border-amber-500 focus-within:ring-1 hover:border-amber-600">
                <div className="bg-brown-700 flex flex-wrap items-center gap-0.5 border-b border-amber-500 px-2 py-1.5">
                    {FORMAT_GROUPS.map((group, groupIndex) => (
                        <div key={groupIndex} className="flex items-center gap-0.5">
                            {groupIndex > 0 && <span className="mx-1.5 h-4 w-px bg-amber-500" />}
                            {group.map(({ key, command }) => (
                                <FormatButton
                                    key={key}
                                    title={ALL_FORMATS[key].title}
                                    label={ALL_FORMATS[key].label}
                                    onClick={() => runCommand(command)}
                                />
                            ))}
                        </div>
                    ))}
                    <div className="flex items-center gap-0.5">
                        <span className="mx-1.5 h-4 w-px bg-amber-500" />
                        {MENTION_KINDS.map((kind) => (
                            <MentionPicker
                                key={kind}
                                kind={kind}
                                mentions={mentions}
                                onPick={(tag) => runCommand(insertText(tag))}
                            />
                        ))}
                    </div>
                </div>
                <div ref={hostRef} />
            </div>
            <p
                id={counterId}
                role="status"
                className={`mt-1.5 text-right text-xs tabular-nums ${counterColour(documentLength)}`}
            >
                {documentLength.toLocaleString("en-GB")} / {DISCORD_MESSAGE_LIMIT.toLocaleString("en-GB")}
            </p>
        </div>
    );
}
