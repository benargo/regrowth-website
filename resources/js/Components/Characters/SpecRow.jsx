import SpecIcon from "@/Components/Characters/SpecIcon";

export default function SpecRow({ spec, isSelected, isRaidSpec, onToggle, onSetRaid, disabled }) {
    return (
        <div className="flex flex-row gap-2">
            <label
                className={`flex flex-1 cursor-pointer items-center gap-3 rounded border px-4 py-3 transition-all ${
                    isSelected
                        ? "border-focus-ring/50 bg-accent/20"
                        : "border-line bg-surface/30 hover:border-focus-ring"
                }`}
            >
                <input
                    type="checkbox"
                    checked={isSelected}
                    onChange={onToggle}
                    className="bg-surface-sunken h-4 w-4 rounded border-line text-line focus:ring-focus-ring focus:ring-offset-0"
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
                        ? "border-focus-ring/50 bg-accent/20"
                        : "border-line bg-surface/30 hover:border-focus-ring"
                }`}
                onClick={(e) => e.stopPropagation()}
            >
                <input
                    type="radio"
                    checked={isRaidSpec}
                    onChange={onSetRaid}
                    disabled={disabled || !isSelected}
                    className="bg-surface-sunken h-3.5 w-3.5 border-line text-body-muted focus:ring-focus-ring focus:ring-offset-0 disabled:cursor-not-allowed"
                />
                <span className="hidden sm:inline">raid spec</span>
            </label>
        </div>
    );
}
