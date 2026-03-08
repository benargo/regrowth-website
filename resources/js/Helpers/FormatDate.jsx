/**
 * Formats a date string into three responsive variants:
 *   short  — 'd/m/Y'   e.g. 08/03/2026
 *   medium — 'd M Y'   e.g. 08 Mar 2026
 *   long   — 'jS F Y'  e.g. 8th March 2026
 */
function ordinal(n) {
    const s = ['th', 'st', 'nd', 'rd'];
    const v = n % 100;
    return n + (s[(v - 20) % 10] || s[v] || s[0]);
}

export default function formatDate(dateString) {
    const date = new Date(dateString);
    const day = date.getDate();
    const dayPadded = String(day).padStart(2, '0');
    const monthPadded = String(date.getMonth() + 1).padStart(2, '0');
    const monthShort = date.toLocaleString('en-GB', { month: 'short' });
    const monthLong = date.toLocaleString('en-GB', { month: 'long' });
    const year = date.getFullYear();
    return {
        short: `${dayPadded}/${monthPadded}/${year}`,
        medium: `${dayPadded} ${monthShort} ${year}`,
        long: `${ordinal(day)} ${monthLong} ${year}`,
    };
}
