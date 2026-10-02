import SettingsPanel, { SettingsGroup } from "@/Components/SettingsPanel";
import SettingRow, { SettingToggle } from "@/Components/SettingRow";

/**
 * The export columns the upload needs, grouped by the column they sit in on
 * GRM's Data Export window so officers can find them in the same place.
 */
const REQUIRED_COLUMNS = [
    ["Name", "Rank", "Level", "Last Online"],
    ["Main/Alt", "Player Alts"],
];

/**
 * The GRM Data Export settings an upload depends on, laid out to mirror the
 * addon's window.
 */
export default function ExportSettings() {
    return (
        <SettingsPanel>
            <SettingRow label="Delimiter">
                <code className="border-ink-600 min-w-10 rounded-xs border bg-black/40 px-3 py-0.5 text-center font-mono font-bold text-white">
                    ,
                </code>
            </SettingRow>

            <SettingsGroup>
                <div className="grid grid-cols-2 gap-x-4 gap-y-2">
                    {REQUIRED_COLUMNS.map((column) => (
                        <div key={column[0]} className="space-y-2">
                            {column.map((name) => (
                                <SettingToggle key={name} label={name} enabled />
                            ))}
                        </div>
                    ))}
                </div>
                <p className="text-secondary-400 font-sans text-sm">
                    GRM ticks most columns by default. Untick every other column.
                </p>
            </SettingsGroup>

            <SettingsGroup label="Under the export box">
                <SettingToggle label="Remove Alt-Code Letters From Names" enabled={false} />
                <SettingToggle label="Auto Include Headers" enabled />
            </SettingsGroup>
        </SettingsPanel>
    );
}
