import Icon from "@/Components/FontAwesome/Icon";
import SpecIcon from "@/Components/Characters/SpecIcon";

export default function SpecRow({ spec, isSelected, isRaidSpec, onToggle, onSetRaid, disabled }) {
    return (
        <label
            className={`flex cursor-pointer items-center gap-3 rounded border px-4 py-3 transition-all ${
                isSelected
                    ? "border-amber-500/50 bg-amber-900/20"
                    : "border-brown-700 bg-brown-800/30 hover:border-brown-600"
            }`}
        >
            <input
                type="checkbox"
                checked={isSelected}
                onChange={onToggle}
                className="h-4 w-4 rounded border-amber-600 bg-brown-900 text-amber-600 focus:ring-amber-500 focus:ring-offset-0"
            />

            <SpecIcon specialization={spec} size={6} />
            <span className={`flex-1 font-medium ${isSelected ? "text-white" : "text-gray-400"}`}>
                {spec.name}
            </span>

            {spec.role && (
                <span className="text-xs text-gray-600 uppercase tracking-wide">{spec.role}</span>
            )}

            <label
                className={`flex items-center gap-1.5 text-xs transition-colors ${
                    isSelected
                        ? "cursor-pointer text-amber-500 hover:text-amber-400"
                        : "cursor-not-allowed text-gray-700"
                }`}
                onClick={(e) => e.stopPropagation()}
            >
                <input
                    type="radio"
                    checked={isRaidSpec}
                    onChange={onSetRaid}
                    disabled={disabled || !isSelected}
                    className="h-3.5 w-3.5 border-amber-600 bg-brown-900 text-amber-500 focus:ring-amber-500 focus:ring-offset-0 disabled:cursor-not-allowed"
                />
                <Icon icon="star" style={isRaidSpec ? "solid" : "light"} className="text-[11px]" />
                <span className="hidden sm:inline">Raid</span>
            </label>
        </label>
    );
}
