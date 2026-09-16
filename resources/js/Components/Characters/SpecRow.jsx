import SpecIcon from "@/Components/Characters/SpecIcon";

export default function SpecRow({ spec, isSelected, isRaidSpec, onToggle, onSetRaid, disabled }) {
    return (
        <div className="flex flex-row gap-2">
            <label
                className={`flex flex-1 cursor-pointer items-center gap-3 rounded border px-4 py-3 transition-all ${
                    isSelected
                        ? "border-ink-500/50 bg-ink-600/20"
                        : "border-ink-600 bg-ground-800/30 hover:border-ink-500"
                }`}
            >
                <input
                    type="checkbox"
                    checked={isSelected}
                    onChange={onToggle}
                    className="bg-ground-900 h-4 w-4 rounded border-ink-600 text-ink-600 focus:ring-ink-500 focus:ring-offset-0"
                />

                <SpecIcon specialization={spec} size={6} />
                <span className={`flex font-medium ${isSelected ? "text-white" : "text-gray-400"}`}>{spec.name}</span>

                {spec.role && (
                    <span className="inline-flex flex-1 flex-row gap-1 text-xs tracking-wide text-gray-400 uppercase">
                        {spec.role_icon_url && <img src={spec.role_icon_url} alt={spec.role} className="h-4 w-4" />}
                        <p>{spec.role}</p>
                    </span>
                )}
            </label>
            <label
                className={`flex flex-initial cursor-pointer items-center gap-3 rounded border px-4 py-3 transition-all ${
                    isRaidSpec
                        ? "border-ink-500/50 bg-ink-600/20"
                        : "border-ink-600 bg-ground-800/30 hover:border-ink-500"
                }`}
                onClick={(e) => e.stopPropagation()}
            >
                <input
                    type="radio"
                    checked={isRaidSpec}
                    onChange={onSetRaid}
                    disabled={disabled || !isSelected}
                    className="bg-ground-900 h-3.5 w-3.5 border-ink-600 text-ink-500 focus:ring-ink-500 focus:ring-offset-0 disabled:cursor-not-allowed"
                />
                <span className="hidden sm:inline">raid spec</span>
            </label>
        </div>
    );
}
