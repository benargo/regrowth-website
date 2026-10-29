export default function RankLabel({ rank, className }) {
    return (
        <p className={`grow-0 flex-row items-center md:flex text-guild-rank-${rank.slug}${className ? ` ${className}` : ""}`}>
            {rank.name}
        </p>
    );
}
