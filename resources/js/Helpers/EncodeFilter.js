/**
 * Encode a filter selection for use as a URL query param.
 *
 * - All selected  → undefined (omit parameter, backend uses defaults)
 * - None selected → 'none'
 * - Subset        → comma-separated string e.g. '1028,1029,1036'
 *
 * @param {number[]} selected - Currently selected IDs
 * @param {{ id: number }[]} options - All available options
 * @returns {string|undefined}
 */
export function encodeFilter(selected, options) {
    if (selected.length === 0) return "none";
    if (selected.length === options.length) return undefined;
    return selected.join(",");
}

/**
 * Decode a filter value received from the server back into an array of IDs.
 *
 * Accepts either the URL-query CSV form or the hydrated DTO form:
 *
 * - null / undefined / 'all'   → defaultIds (use the provided defaults)
 * - 'none' / empty array []    → [] (nothing selected)
 * - '1028,1029' / [1028, 1029] → [1028, 1029]
 *
 * @param {string|number[]|null|undefined} value - The raw filter value from the server
 * @param {number[]} defaultIds - IDs to use when no filter is set
 * @returns {number[]}
 */
export function decodeFilter(value, defaultIds) {
    if (value === null || value === undefined || value === "all") return defaultIds;
    if (Array.isArray(value)) return value;
    if (value === "none") return [];
    return value.split(",").map(Number);
}
