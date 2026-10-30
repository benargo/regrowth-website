import { EditorSelection } from "@codemirror/state";

const HEADING_PREFIX_PATTERN = /^#{1,6} /;
const BULLET_PREFIX = "- ";
const LINK_PLACEHOLDER_TEXT = "link text";
const LINK_PLACEHOLDER_URL = "https://";

/**
 * The lines touched by any selection range, each listed once, in order.
 *
 * @param {import("@codemirror/state").EditorState} state
 * @returns {Array<import("@codemirror/state").Line>}
 */
function selectedLines(state) {
    const lines = new Map();

    for (const range of state.selection.ranges) {
        const firstLine = state.doc.lineAt(range.from).number;
        const lastLine = state.doc.lineAt(range.to).number;

        for (let number = firstLine; number <= lastLine; number++) {
            lines.set(number, state.doc.line(number));
        }
    }

    return [...lines.values()];
}

/**
 * Applies line-start changes and keeps the selection on the same text.
 *
 * @param {import("@codemirror/view").EditorView} view
 * @param {Array<{ from: number, to: number, insert: string }>} changes
 * @returns {boolean}
 */
function dispatchLineChanges(view, changes) {
    const changeSet = view.state.changes(changes);

    view.dispatch({
        changes: changeSet,
        selection: view.state.selection.map(changeSet),
        scrollIntoView: true,
        userEvent: "input",
    });
    view.focus();

    return true;
}

/**
 * A command that makes the selected lines `##` (level 2) or `###` (level 3)
 * headings, or plain lines when every one is already a heading of that level.
 *
 * @param {2|3} level
 * @returns {(view: import("@codemirror/view").EditorView) => boolean}
 */
export function toggleHeading(level) {
    const prefix = `${"#".repeat(level)} `;

    return (view) => {
        const lines = selectedLines(view.state);
        const isEveryLineHeading = lines.every((line) => line.text.startsWith(prefix));

        const changes = lines.map((line) => {
            const existingPrefix = HEADING_PREFIX_PATTERN.exec(line.text)?.[0] ?? "";

            return {
                from: line.from,
                to: line.from + existingPrefix.length,
                insert: isEveryLineHeading ? "" : prefix,
            };
        });

        return dispatchLineChanges(view, changes);
    };
}

/**
 * A command that puts `- ` before each selected line, or removes it when
 * every selected line already starts with it.
 *
 * @param {import("@codemirror/view").EditorView} view
 * @returns {boolean}
 */
export function toggleBulletList(view) {
    const lines = selectedLines(view.state);
    const isEveryLineBullet = lines.every((line) => line.text.startsWith(BULLET_PREFIX));

    const changes = lines
        .filter((line) => isEveryLineBullet || !line.text.startsWith(BULLET_PREFIX))
        .map((line) =>
            isEveryLineBullet
                ? { from: line.from, to: line.from + BULLET_PREFIX.length, insert: "" }
                : { from: line.from, to: line.from, insert: BULLET_PREFIX },
        );

    return dispatchLineChanges(view, changes);
}

/**
 * How many copies of `character` sit directly on both sides of `from`–`to`:
 * the shorter of the run before `from` and the run after `to`.
 *
 * @param {import("@codemirror/state").Text} doc
 * @param {number} from
 * @param {number} to
 * @param {string} character
 * @returns {number}
 */
function surroundingRunLength(doc, from, to, character) {
    let before = 0;
    let after = 0;

    while (from - before > 0 && doc.sliceString(from - before - 1, from - before) === character) {
        before++;
    }

    while (to + after < doc.length && doc.sliceString(to + after, to + after + 1) === character) {
        after++;
    }

    return Math.min(before, after);
}

/**
 * Whether `from`–`to` is wrapped in `marker`, alone (`*x*`, `**x**`) or
 * combined with the other marker of the same character (`***x***`).
 *
 * @param {import("@codemirror/state").Text} doc
 * @param {number} from
 * @param {number} to
 * @param {string} marker
 * @returns {boolean}
 */
function isWrappedBy(doc, from, to, marker) {
    const runLength = surroundingRunLength(doc, from, to, marker[0]);

    return runLength === marker.length || runLength === 3;
}

/**
 * A command that wraps each selection in `marker` (`**`, `*` or `__`), or
 * unwraps it when it is already wrapped. An empty selection gets a pair of
 * markers with the cursor between them.
 *
 * @param {string} marker
 * @returns {(view: import("@codemirror/view").EditorView) => boolean}
 */
export function wrapSelection(marker) {
    return (view) => {
        const { state } = view;

        const transaction = state.changeByRange((range) => {
            if (!range.empty && isWrappedBy(state.doc, range.from, range.to, marker)) {
                return {
                    changes: [
                        { from: range.from - marker.length, to: range.from, insert: "" },
                        { from: range.to, to: range.to + marker.length, insert: "" },
                    ],
                    range: EditorSelection.range(range.from - marker.length, range.to - marker.length),
                };
            }

            return {
                changes: [
                    { from: range.from, insert: marker },
                    { from: range.to, insert: marker },
                ],
                range: EditorSelection.range(range.from + marker.length, range.to + marker.length),
            };
        });

        view.dispatch(state.update(transaction, { scrollIntoView: true, userEvent: "input" }));
        view.focus();

        return true;
    };
}

/**
 * A command that turns each selection into `[text](https://)` and selects
 * the URL, or inserts `[link text](https://)` with the text selected when
 * nothing is selected.
 *
 * @param {import("@codemirror/view").EditorView} view
 * @returns {boolean}
 */
export function insertLink(view) {
    const { state } = view;

    const transaction = state.changeByRange((range) => {
        const linkText = range.empty ? LINK_PLACEHOLDER_TEXT : state.sliceDoc(range.from, range.to);
        const link = `[${linkText}](${LINK_PLACEHOLDER_URL})`;
        const selectedRange = range.empty
            ? EditorSelection.range(range.from + 1, range.from + 1 + linkText.length)
            : EditorSelection.range(
                  range.from + linkText.length + 3,
                  range.from + linkText.length + 3 + LINK_PLACEHOLDER_URL.length,
              );

        return {
            changes: { from: range.from, to: range.to, insert: link },
            range: selectedRange,
        };
    });

    view.dispatch(state.update(transaction, { scrollIntoView: true, userEvent: "input" }));
    view.focus();

    return true;
}

/**
 * A command that replaces each selection with `text` and puts the cursor
 * after it.
 *
 * @param {string} text
 * @returns {(view: import("@codemirror/view").EditorView) => boolean}
 */
export function insertText(text) {
    return (view) => {
        view.dispatch(view.state.replaceSelection(text), { scrollIntoView: true, userEvent: "input" });
        view.focus();

        return true;
    };
}
