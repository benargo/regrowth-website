import { Deferred } from "@inertiajs/react";
import ExportPanel, { ExportPanelSkeleton } from "@/Components/LootTool/ExportPanel";
import Dropdown from "@/Components/Dropdown";
import EmptyState from "@/Components/EmptyState";
import Icon from "@/Components/FontAwesome/Icon";
import FreshnessAlerts from "@/Components/GuildRosterManager/FreshnessAlerts";

/**
 * The selected game version's addon export: its GRM freshness alerts, a line naming the
 * version, a dropdown of `formats`, and the `selectedFormat`'s data in an ExportPanel. Shows an
 * empty state when there is no game version or the version has no export. `exports` maps each
 * format's key to its data, which shows a skeleton while undefined; `grmFreshness` is deferred.
 * Each format holds the dropdown `label`, the panel's title, description, copy labels and
 * `wrap`, plus an optional `display` that turns the raw export into the text shown.
 * onSelectFormat receives the chosen format's key.
 */
export default function GameVersionExport({
    gameVersion,
    hasExport,
    exports,
    grmFreshness,
    formats,
    selectedFormat,
    onSelectFormat,
}) {
    if (gameVersion === null) {
        return (
            <div className="rounded-md border border-dashed border-amber-700/70">
                <EmptyState icon="box-open" message="No game version is set up yet." />
            </div>
        );
    }

    if (!hasExport) {
        return (
            <div className="rounded-md border border-dashed border-amber-700/70">
                <EmptyState
                    icon="box-open"
                    message={`${gameVersion.title} has no current guild roster, so it has no addon export.`}
                />
            </div>
        );
    }

    const format = formats[selectedFormat];
    const exportedData = exports[selectedFormat];

    return (
        <>
            <Deferred
                data="grmFreshness"
                fallback={
                    <div aria-hidden="true" className="bg-brown-800/50 mb-6 h-16 animate-pulse rounded md:mx-20" />
                }
            >
                <FreshnessAlerts freshness={grmFreshness} />
            </Deferred>

            <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
                <p className="text-gray-200">
                    Addon data for <strong className="text-white">{gameVersion.title}</strong>. Use the game version
                    switcher to export another version.
                </p>
                <Dropdown>
                    <Dropdown.Trigger>
                        <button
                            type="button"
                            className="flex flex-none items-center justify-between gap-2 rounded border border-amber-600 px-4 py-2 text-amber-500 transition-colors hover:bg-amber-600/20"
                        >
                            {format.label}
                            <Icon icon="chevron-down" style="solid" className="text-xs" />
                        </button>
                    </Dropdown.Trigger>
                    <Dropdown.Content align="right" width="48">
                        <div className="bg-brown-800 rounded-md border border-amber-600 shadow-lg">
                            {Object.entries(formats).map(([key, option]) => (
                                <Dropdown.Button key={key} onClick={() => onSelectFormat(key)}>
                                    {option.label}
                                </Dropdown.Button>
                            ))}
                        </div>
                    </Dropdown.Content>
                </Dropdown>
            </div>

            <Deferred key={selectedFormat} data={selectedFormat} fallback={<ExportPanelSkeleton />}>
                <ExportPanel
                    title={format.title}
                    description={format.description}
                    copyValue={exportedData}
                    display={format.display ? format.display(exportedData ?? "") : exportedData}
                    copyLabel={format.copyLabel}
                    copySuccessMessage={format.copySuccessMessage}
                    wrap={format.wrap}
                />
            </Deferred>
        </>
    );
}
