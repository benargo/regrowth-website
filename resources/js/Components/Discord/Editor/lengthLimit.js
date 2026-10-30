import { Annotation, EditorState, Transaction } from "@codemirror/state";
import { discordTagPattern } from "@/Helpers/discordTags";

const GRAPHEME_SEGMENTER = new Intl.Segmenter(undefined, { granularity: "grapheme" });

/**
 * Marks a transaction that replaces the document with the `value` prop.
 * The length limit and the editor's change listener both ignore it.
 */
export const externalSync = Annotation.define();

/**
 * The length of the longest run of whole graphemes at the start of `text`
 * that fits within `budget` UTF-16 code units.
 *
 * @param {string} text
 * @param {number} budget
 * @returns {number}
 */
function graphemeBoundaryWithin(text, budget) {
    let end = 0;

    for (const { segment } of GRAPHEME_SEGMENTER.segment(text)) {
        if (end + segment.length > budget) {
            break;
        }

        end += segment.length;
    }

    return end;
}

/**
 * The cut position moved back to the start of any Discord tag it falls inside.
 *
 * @param {string} text
 * @param {number} end
 * @returns {number}
 */
function beforeSplitTag(text, end) {
    for (const match of text.matchAll(discordTagPattern())) {
        if (match.index < end && end < match.index + match[0].length) {
            return match.index;
        }
    }

    return end;
}

/**
 * A paste or drop cut down to the room left under the limit, or no
 * transaction at all when nothing fits or several ranges were changed.
 *
 * @param {Transaction} transaction
 * @param {number} limit
 * @param {(cutCount: number) => void} onTrim
 * @returns {import("@codemirror/state").TransactionSpec | readonly import("@codemirror/state").TransactionSpec[]}
 */
function trimInsertion(transaction, limit, onTrim) {
    const changedRanges = [];

    transaction.changes.iterChanges((fromA, toA, fromB, toB, inserted) => {
        changedRanges.push({ from: fromA, to: toA, text: inserted.toString() });
    });

    if (changedRanges.length !== 1) {
        onTrim(0);

        return [];
    }

    const [{ from, to, text }] = changedRanges;
    const budget = limit - (transaction.startState.doc.length - (to - from));
    const keptLength = budget > 0 ? beforeSplitTag(text, graphemeBoundaryWithin(text, budget)) : 0;

    if (keptLength === 0) {
        onTrim(0);

        return [];
    }

    onTrim(text.length - keptLength);

    return {
        changes: { from, to, insert: text.slice(0, keptLength) },
        selection: { anchor: from + keptLength },
        scrollIntoView: true,
        userEvent: transaction.annotation(Transaction.userEvent),
    };
}

/**
 * Keeps the document within `limit` UTF-16 code units. A paste or drop that
 * doesn't fit is cut at a grapheme boundary before any Discord tag the cut
 * would split, and `onTrim` receives the number of characters removed. A
 * paste into several ranges at once, and any other change that would pass
 * the limit, is rejected and `onTrim` receives 0. Transactions marked `externalSync`, and those that
 * don't lengthen the document, pass untouched.
 *
 * @param {{ limit: number, onTrim: (cutCount: number) => void }} options
 * @returns {import("@codemirror/state").Extension}
 */
export function lengthLimit({ limit, onTrim }) {
    return EditorState.transactionFilter.of((transaction) => {
        if (!transaction.docChanged || transaction.annotation(externalSync)) {
            return transaction;
        }

        const newLength = transaction.newDoc.length;

        if (newLength <= limit || newLength <= transaction.startState.doc.length) {
            return transaction;
        }

        if (transaction.isUserEvent("input.paste") || transaction.isUserEvent("input.drop")) {
            return trimInsertion(transaction, limit, onTrim);
        }

        onTrim(0);

        return [];
    });
}
