import { useRef, useState } from "react";
import { Deferred, Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import Alert from "@/Components/Alert";
import FlashMessage from "@/Components/FlashMessage";
import Icon from "@/Components/FontAwesome/Icon";
import SharedHeader from "@/Components/SharedHeader";
import TabNav from "@/Components/TabNav";

export default function AddonExport({ exportedData, grmFreshness }) {
    const [flashSuccess, setFlashSuccess] = useState(null);
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

    function exportAddonData() {
        navigator.clipboard.writeText(exportedData).then(() => {
            setFlashSuccess("Addon data copied to clipboard!");
        });
    }

    function grmDataIsOutdated() {
        let lastModified = new Date(grmFreshness?.lastModified);
        let now = new Date();
        let diffInDays = (now - lastModified) / (1000 * 60 * 60 * 24);
        return diffInDays > 7;
    }

    return (
        <Master title="Export Addon Data">
            <SharedHeader title="Export Addon Data" backgroundClass="bg-officer-meeting" />
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    <TabNav
                        tabs={[
                            { name: "base64", label: "Base64", href: route("dashboard.addon.export") },
                            { name: "json", label: "JSON", href: route("dashboard.addon.export.json") },
                            { name: "schema", label: "Schema", href: route("dashboard.addon.export.schema") },
                            { name: "settings", label: "Settings", href: route("dashboard.addon.settings") },
                        ]}
                        currentTab="base64"
                    />
                    <Deferred data="grmFreshness" fallback={<div></div>}>
                        {grmFreshness?.dataIsStale && (
                            <div className="mb-6 md:mx-20">
                                <Alert type="error">
                                    <div className="flex flex-col items-center gap-2 md:flex-row">
                                        <div className="flex-auto">
                                            <h2 className="mb-1 text-lg font-bold">GRM data out of date</h2>
                                            <p>
                                                The GRM data used to generate this addon data is missing raiders. Please
                                                consider uploading a fresh GRM export to ensure your addon data is up to
                                                date.
                                            </p>
                                        </div>
                                        <div className="flex-auto">
                                            <Link
                                                href={route("dashboard.grm-upload.form")}
                                                className="inline-flex items-center rounded-md border border-transparent bg-red-600 p-4 text-sm font-semibold text-white transition duration-150 ease-in-out hover:bg-red-800 focus:outline-none focus:ring-2 focus:ring-red-500 focus:ring-offset-2 disabled:opacity-25"
                                            >
                                                <Icon icon="file-upload" style="solid" className="mr-2" />
                                                <span className="whitespace-nowrap">Upload GRM Data</span>
                                            </Link>
                                        </div>
                                    </div>
                                </Alert>
                            </div>
                        )}
                        {!grmFreshness?.dataIsStale && grmDataIsOutdated() && (
                            <div className="mb-6 md:mx-20">
                                <Alert type="warning">
                                    <div className="flex flex-col items-center gap-2 md:flex-row">
                                        <div className="flex-auto">
                                            <h2 className="mb-1 text-lg font-bold">Old GRM data detected</h2>
                                            <p>
                                                The GRM data used to generate this addon data is over 7 days old (last
                                                updated on {new Date(grmFreshness?.lastModified).toLocaleDateString()}).
                                                Please consider uploading a fresh GRM export to ensure your addon data
                                                is up to date.
                                            </p>
                                        </div>
                                        <div className="flex-initial">
                                            <Link
                                                href={route("dashboard.grm-upload.form")}
                                                className="inline-flex items-center rounded-md border border-transparent bg-yellow-600 p-4 text-sm font-semibold text-white transition duration-150 ease-in-out hover:bg-yellow-800 focus:outline-none focus:ring-2 focus:ring-yellow-500 focus:ring-offset-2 disabled:opacity-25"
                                            >
                                                <Icon icon="file-upload" style="solid" className="mr-2" />
                                                <span className="whitespace-nowrap">Upload GRM Data</span>
                                            </Link>
                                        </div>
                                    </div>
                                </Alert>
                            </div>
                        )}
                    </Deferred>
                    <div className="flex flex-row items-baseline space-x-4">
                        <div className="flex-1">
                            <p>
                                This is the version you should import into the addon. Click the button to export the
                                addon data to your clipboard.
                            </p>
                        </div>
                        <button
                            className="flex flex-none items-center justify-center rounded bg-blue-600 px-4 py-2 font-bold text-white hover:bg-blue-800"
                            onClick={exportAddonData}
                        >
                            <Icon icon="copy" style="solid" className="mr-2" />
                            <span>Copy Addon Data</span>
                        </button>
                    </div>
                    <Deferred
                        data="exportedData"
                        fallback={
                            <div className="mt-6">
                                <div className="flex min-h-64 w-full items-center justify-center rounded border border-gray-800 bg-brown-800/50 p-4">
                                    <p className="animate-pulse text-gray-400">
                                        Loading data... this may take a while.
                                    </p>
                                </div>
                            </div>
                        }
                    >
                        <div className="mt-6">
                            <div
                                ref={dataRef}
                                onClick={selectAllContent}
                                className="max-h-[600px] min-h-64 w-full cursor-pointer overflow-auto break-all rounded border border-gray-800 bg-brown-800/50 p-4 text-white"
                            >
                                {exportedData?.length === 0 && <p>No addon data available.</p>}
                                {exportedData}
                            </div>
                        </div>
                    </Deferred>
                </div>
            </div>
            <FlashMessage type="success" message={flashSuccess} onDismiss={() => setFlashSuccess(null)} />
        </Master>
    );
}
