export default function getItemRaid(item) {
    return item.data.raids?.[0] ?? null;
}
