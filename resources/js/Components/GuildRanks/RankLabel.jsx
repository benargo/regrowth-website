export default function RankLabel({ rank, className }) {
    const slug = rank.name
        .toLowerCase()
        .replace(/\s+/g, "-")
        .replace(/[^a-z0-9-]/g, "");

    return (
        <p className={`grow-0 flex-row items-center md:flex text-guild-rank-${slug}${className ? ` ${className}` : ""}`}>
            {rank.name}
        </p>
    );
}
