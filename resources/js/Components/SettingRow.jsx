import Icon from "@/Components/FontAwesome/Icon";

/**
 * A read-only setting: its label alongside the value it should be set to.
 */
export default function SettingRow({ label, children }) {
    return (
        <div className="flex items-center gap-3">
            <span>{label}</span>
            {children}
        </div>
    );
}

/**
 * A read-only checkbox setting, drawn as a ticked or empty box so it can be
 * compared at a glance with the checkbox it describes.
 */
export function SettingToggle({ label, enabled }) {
    return (
        <div className="flex items-start gap-2">
            <span
                aria-hidden="true"
                className="border-ink-600 bg-ground-800 mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-xs border"
            >
                {enabled && <Icon icon="check" style="solid" className="text-sm text-yellow-400" />}
            </span>
            <span>
                {label}
                <span className="sr-only">: {enabled ? "on" : "off"}</span>
            </span>
        </div>
    );
}
