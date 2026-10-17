import SpecIcon from "@/Components/Characters/SpecIcon";
import { Checkbox } from "@/Components/FormControls";

export default function SpecRow({ spec, isSelected, isRaidSpec, onToggle, onSetRaid, disabled }) {
    return (
        <div className="flex flex-row gap-2">
            <label
                className={`flex flex-1 cursor-pointer items-center gap-3 rounded border px-4 py-3 transition-colors ${
                    isSelected
                        ? "border-amber-500/50 bg-amber-600/20"
                        : "bg-brown-800/30 border-amber-600 hover:border-amber-500"
                }`}
            >
                <Checkbox checked={isSelected} onChange={onToggle} />

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
                className={`flex flex-initial cursor-pointer items-center gap-3 rounded border px-4 py-3 transition-colors ${
                    isRaidSpec
                        ? "border-amber-500/50 bg-amber-600/20"
                        : "bg-brown-800/30 border-amber-600 hover:border-amber-500"
                }`}
                onClick={(e) => e.stopPropagation()}
            >
                <input
                    type="radio"
                    checked={isRaidSpec}
                    onChange={onSetRaid}
                    aria-label={`Set ${spec.name} as raid spec`}
                    disabled={disabled || !isSelected}
                    className="bg-brown-900 h-3.5 w-3.5 border-amber-600 text-amber-500 focus:ring-amber-500 focus:ring-offset-0 disabled:cursor-not-allowed"
                />
                <span className="max-sm:sr-only">raid spec</span>
            </label>
        </div>
    );
}
