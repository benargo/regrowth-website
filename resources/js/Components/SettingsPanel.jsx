/**
 * A framed summary of settings someone should match elsewhere, such as an
 * addon's options window. Pair with `SettingRow` and `SettingToggle`.
 */
export default function SettingsPanel({ children, className = "" }) {
    return (
        <div className={`border-ink-700 bg-ground-900 space-y-4 rounded-md border p-4 font-serif ${className}`}>
            {children}
        </div>
    );
}

/**
 * A group of settings within a `SettingsPanel`, introduced by a divider label
 * that tells the reader where to find them.
 */
export function SettingsGroup({ label, children }) {
    return (
        <div className="space-y-3">
            {label && (
                <div className="text-secondary-400 flex items-center gap-3 text-sm">
                    <span className="shrink-0">{label}</span>
                    <span aria-hidden="true" className="bg-ink-700 h-px grow" />
                </div>
            )}
            {children}
        </div>
    );
}
