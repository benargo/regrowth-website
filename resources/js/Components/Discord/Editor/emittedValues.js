/**
 * Whether `value` is one of the documents the editor has passed to
 * `onChange` and not yet received back. A match drops the oldest matching
 * emission and every emission before it from `pendingEmissions`.
 *
 * @param {string[]} pendingEmissions
 * @param {string} value
 * @returns {boolean}
 */
export function consumeEmittedValue(pendingEmissions, value) {
    const index = pendingEmissions.indexOf(value);

    if (index === -1) {
        return false;
    }

    pendingEmissions.splice(0, index + 1);

    return true;
}
