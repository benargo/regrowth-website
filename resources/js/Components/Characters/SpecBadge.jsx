import Icon from "@/Components/FontAwesome/Icon";
import SpecIcon from "@/Components/Characters/SpecIcon";

export default function SpecBadge({ spec, isRaid }) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded border px-2.5 py-1 text-xs font-medium transition-colors ${
                isRaid
                    ? "border-amber-500/60 bg-amber-900/30 text-amber-300"
                    : "border-brown-600 bg-brown-800/50 text-gray-400"
            }`}
        >
            <SpecIcon specialization={spec} size={4} />
            {spec.name}
            {isRaid && (
                <Icon icon="star" style="solid" className="text-[10px] text-amber-400" />
            )}
        </span>
    );
}
