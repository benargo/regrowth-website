export default function getDayDifference(referenceDate, targetDate) {
    if (!referenceDate || !targetDate) return null;
    const ref = new Date(referenceDate);
    const tar = new Date(targetDate);
    const refDay = new Date(ref.getFullYear(), ref.getMonth(), ref.getDate());
    const tarDay = new Date(tar.getFullYear(), tar.getMonth(), tar.getDate());
    const diff = Math.round((tarDay - refDay) / 86400000);
    if (diff === 0) return null;
    const abs = Math.abs(diff);
    return diff > 0 ? `${abs} ${abs === 1 ? "day" : "days"} after` : `${abs} ${abs === 1 ? "day" : "days"} before`;
}
