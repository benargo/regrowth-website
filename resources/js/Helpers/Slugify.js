/**
 * Latin letters that NFKD normalisation doesn't break down into a base
 * letter, spelled the way Laravel's Str::slug spells them.
 */
const TRANSLITERATIONS = {
    ß: "ss",
    æ: "ae",
    œ: "oe",
    ø: "o",
    ł: "l",
    ð: "d",
    đ: "d",
    þ: "th",
    ı: "i",
    ħ: "h",
    ŧ: "t",
    ĸ: "k",
};

/**
 * Converts a string to a slug the way Laravel's Str::slug does, so
 * "tHe Burning CruSade" becomes "the-burning-crusade" and "Straße" becomes
 * "strasse". Letters from other scripts, such as Cyrillic or Greek, are kept
 * as they are so the server can transliterate them when it saves the slug.
 * Without `trim`, a trailing hyphen is kept so someone typing a space can
 * carry on to the next word; trim it once they've finished.
 */
export default function slugify(value, { trim = false } = {}) {
    const slug = value
        .toLowerCase()
        .normalize("NFKD")
        .replace(/[\p{M}\p{Lm}]/gu, "")
        .replace(/[ßæœøłðđþıħŧĸ]/g, (letter) => TRANSLITERATIONS[letter])
        .replace(/_/g, "-")
        .replace(/@/g, "-at-")
        .replace(/[^\p{L}\p{N}\s-]/gu, "")
        .replace(/[\s-]+/g, "-")
        .replace(/^-+/, "");

    return trim ? slug.replace(/-+$/, "") : slug;
}
