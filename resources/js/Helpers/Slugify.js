/**
 * Converts a string to a slug the way Laravel's Str::slug does, so
 * "tHe Burning CruSade" becomes "the-burning-crusade". Without `trim`, a
 * trailing hyphen is kept so someone typing a space can carry on to the
 * next word; trim it once they've finished.
 */
export default function slugify(value, { trim = false } = {}) {
    const slug = value
        .normalize("NFKD")
        .replace(/[̀-ͯ]/g, "")
        .replace(/_/g, "-")
        .replace(/@/g, "-at-")
        .toLowerCase()
        .replace(/[^a-z0-9\s-]/g, "")
        .replace(/[\s-]+/g, "-")
        .replace(/^-+/, "");

    return trim ? slug.replace(/-+$/, "") : slug;
}
