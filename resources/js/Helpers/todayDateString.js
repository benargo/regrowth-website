/**
 * Today's local date as YYYY-MM-DD, so it compares directly with the resources' date strings.
 */
export default function todayDateString() {
    const now = new Date();
    const month = String(now.getMonth() + 1).padStart(2, "0");
    const day = String(now.getDate()).padStart(2, "0");

    return `${now.getFullYear()}-${month}-${day}`;
}
