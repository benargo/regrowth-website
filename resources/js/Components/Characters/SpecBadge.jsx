import Icon from "@/Components/FontAwesome/Icon";
import SpecIcon from "@/Components/Characters/SpecIcon";

export default function SpecBadge({ spec, isRaid }) {
    return (
        <span
            className={`inline-flex items-center gap-1.5 rounded border px-2.5 py-1 text-xs font-medium transition-colors ${
                isRaid
                    ? "border-camel-500/60 bg-camel-900/30 text-camel-300"
                    : "border-forever-500 bg-forever-800/50 text-gray-400"
            }`}
        >
            <SpecIcon specialization={spec} size={4} />
            {spec.name}
            {isRaid && (
                <Icon icon="star" style="solid" className="text-[10px] text-camel-400" />
            )}
        </span>
    );
}
