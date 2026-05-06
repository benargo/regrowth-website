export default function formatDuration({ milliseconds = 0, seconds = 0, minutes = 0 } = {}) {
    const totalMinutes = Math.floor(milliseconds / 60000 + seconds / 60 + minutes);
    const h = Math.floor(totalMinutes / 60);
    const m = totalMinutes % 60;
    if (h === 0) return `${m}m`;
    if (m === 0) return `${h}h`;
    return `${h}h ${m}m`;
}
