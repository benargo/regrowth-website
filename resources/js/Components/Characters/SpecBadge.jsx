import Icon from "@/Components/FontAwesome/Icon";
import SpecIcon from "@/Components/Characters/SpecIcon";

export default function SpecBadge({ spec, isRaid }) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded border px-2.5 py-1 text-xs font-medium transition-colors ${
                isRaid
                    ? "border-ink-500/60 bg-ink-600/30 text-ink-300"
                    : "border-ink-500 bg-ground-800/50 text-secondary-400"
            }`}
        >
            <SpecIcon specialization={spec} size={4} />
            {spec.name}
            {isRaid && (
                <Icon icon="star" style="solid" className="text-[10px] text-ink-400" />
            )}
        </span>
    );
}
