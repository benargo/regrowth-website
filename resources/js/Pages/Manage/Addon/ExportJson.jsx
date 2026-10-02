import { useRef } from "react";
import { Deferred } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import CopyButton from "@/Components/CopyButton";
import FreshnessAlerts from "@/Components/GuildRosterManager/FreshnessAlerts";
import SharedHeader from "@/Components/SharedHeader";
import TabNav from "@/Components/TabNav";
import PageContainer from "@/Components/PageContainer";

export default function AddonExportJson({ exportedData, grmFreshness }) {
    const dataRef = useRef(null);

    function selectAllContent() {
        if (dataRef.current) {
            const selection = window.getSelection();
            const range = document.createRange();
            range.selectNodeContents(dataRef.current);
            selection.removeAllRanges();
            selection.addRange(range);
        }
    }

    return (
        <Master title="Export Addon Data">
            <SharedHeader title="Export Addon Data" backgroundClass="bg-officer-meeting" />
            <PageContainer>
                <TabNav
                    tabs={[
                        { name: "base64", label: "Base64", href: route("management.addon.export") },
                        { name: "json", label: "JSON", href: route("management.addon.export.json") },
                        { name: "schema", label: "Schema", href: route("management.addon.export.schema") },
                        { name: "settings", label: "Settings", href: route("management.addon.settings") },
                    ]}
                    currentTab="json"
                />
                <Deferred data="grmFreshness" fallback={<div></div>}>
                    <FreshnessAlerts freshness={grmFreshness} />
                </Deferred>
                <div className="flex flex-row items-baseline space-x-4">
                    <div className="flex-1">
                        <p>Click the button to copy the JSON data to your clipboard.</p>
                    </div>
                    <CopyButton
                        getValue={() => exportedData}
                        label="Copy JSON Data"
                        successMessage="JSON data copied to clipboard!"
                        className="flex flex-none items-center justify-center rounded bg-blue-600 px-4 py-2 font-bold text-white hover:bg-blue-800"
                    />
                </div>
                <Deferred
                    data="exportedData"
                    fallback={
                        <div className="mt-6">
                            <div className="flex min-h-64 w-full items-center justify-center rounded border border-secondary-800 bg-ground-800/50 p-4">
                                <p className="animate-pulse text-secondary-400">Loading data... this may take a while.</p>
                            </div>
                        </div>
                    }
                >
                    <div className="mt-6">
                        <pre
                            ref={dataRef}
                            onClick={selectAllContent}
                            className="max-h-[600px] min-h-64 w-full cursor-pointer overflow-auto rounded border border-secondary-800 bg-ground-800/50 p-4 text-sm text-white"
                        >
                            {exportedData?.length === 0 && "No addon data available."}
                            {exportedData?.replace(/\\u([0-9a-fA-F]{4})/g, (_, hex) =>
                                String.fromCharCode(parseInt(hex, 16)),
                            )}
                        </pre>
                    </div>
                </Deferred>
            </PageContainer>
        </Master>
    );
}
