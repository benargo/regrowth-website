/**
 * Autocomplete's onChange fires with either free-typed search text or the
 * selected option's value (a positive integer ID string, per getOptionValue).
 * This distinguishes the two so callers can tell selection from typing.
 */
export default function parseAutocompleteSelection(value) {
    const id = Number(value);

    return Number.isInteger(id) && id > 0 ? id : null;
}
