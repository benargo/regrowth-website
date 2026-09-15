import Icon from "@/Components/FontAwesome/Icon";
import SpecIcon from "@/Components/Characters/SpecIcon";

export default function SpecBadge({ spec, isRaid }) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded border px-2.5 py-1 text-xs font-medium transition-colors ${
                isRaid
                    ? "border-focus-ring/60 bg-accent/30 text-body-bright"
                    : "border-focus-ring bg-surface/50 text-gray-400"
            }`}
        >
            <SpecIcon specialization={spec} size={4} />
            {spec.name}
            {isRaid && (
                <Icon icon="star" style="solid" className="text-[10px] text-body" />
            )}
        </span>
    );
}
