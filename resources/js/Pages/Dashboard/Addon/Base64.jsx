import { useRef, useState } from 'react';
import { Deferred, Link } from '@inertiajs/react';
import Master from '@/Layouts/Master';
import Alert from '@/Components/Alert';
import SharedHeader from '@/Components/SharedHeader';
import FlashMessage from '@/Components/FlashMessage';
import TabNav from '@/Components/TabNav';

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
            setFlashSuccess('Addon data copied to clipboard!');
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
                            { name: 'base64', label: 'Base64', href: route('dashboard.addon.export') },
                            { name: 'json', label: 'JSON', href: route('dashboard.addon.export.json') },
                            { name: 'schema', label: 'Schema', href: route('dashboard.addon.export.schema') },
                        ]}
                        currentTab="base64"
                    />
                    <Deferred data="grmFreshness" fallback={<div></div>}>
                        {grmFreshness?.dataIsStale && (
                            <div className="md:mx-20 mb-6">
                                <Alert type="error">
                                    <div className="flex flex-col md:flex-row items-center gap-2">
                                        <div className="flex-auto">
                                            <h2 className="font-bold text-lg mb-1">GRM data out of date</h2>
                                            <p>The GRM data used to generate this addon data is missing raiders. Please consider uploading a fresh GRM export to ensure your addon data is up to date.</p>
                                        </div>
                                        <div className="flex-auto">
                                            <Link 
                                                href={route('dashboard.grm-upload.form')}
                                                className="inline-flex items-center p-4 bg-red-600 hover:bg-red-800 border border-transparent rounded-md font-semibold text-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-red-500 disabled:opacity-25 transition ease-in-out duration-150"
                                            >
                                                <i className="fas fa-file-upload mr-2"></i>
                                                <span className="whitespace-nowrap">Upload GRM Data</span>
                                            </Link>
                                        </div>
                                    </div>
                                </Alert>
                            </div>
                        )}
                        {!grmFreshness?.dataIsStale && grmDataIsOutdated() && (
                            <div className="md:mx-20 mb-6">
                                <Alert type="warning">
                                    <div className="flex flex-col md:flex-row items-center gap-2">
                                        <div className="flex-auto">
                                            <h2 className="font-bold text-lg mb-1">Old GRM data detected</h2>
                                            <p>The GRM data used to generate this addon data is over 7 days old (last updated on {new Date(grmFreshness?.lastModified).toLocaleDateString()}). Please consider uploading a fresh GRM export to ensure your addon data is up to date.</p>
                                        </div>
                                        <div className="flex-initial">
                                            <Link 
                                                href={route('dashboard.grm-upload.form')}
                                                className="inline-flex items-center p-4 bg-yellow-600 hover:bg-yellow-800 border border-transparent rounded-md font-semibold text-sm text-white focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-yellow-500 disabled:opacity-25 transition ease-in-out duration-150"
                                            >
                                                <i className="fas fa-file-upload mr-2"></i>
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
                            <p>This is the version you should import into the addon. Click the button to export the addon data to your clipboard.</p> 
                        </div>
                        <button
                            className="flex-none flex items-center justify-center bg-blue-600 hover:bg-blue-800 text-white font-bold py-2 px-4 rounded"
                            onClick={exportAddonData}
                        >
                            <i className="fas fa-copy mr-2"></i>
                            <span>Copy Addon Data</span>
                        </button>
                    </div>
                    <Deferred data="exportedData" fallback={
                        <div className="mt-6">
                            <div className="w-full min-h-64 bg-brown-800/50 border border-gray-800 p-4 rounded flex items-center justify-center">
                                <p className="text-gray-400 animate-pulse">Loading data... this may take a while.</p>
                            </div>
                        </div>
                    }>
                        <div className="mt-6">
                            <div
                                ref={dataRef}
                                onClick={selectAllContent}
                                className="w-full min-h-64 max-h-[600px] overflow-auto bg-brown-800/50 border border-gray-800 text-white p-4 rounded break-all cursor-pointer"
                            >
                                {exportedData?.length === 0 && (<p>No addon data available.</p>)}
                                {exportedData}
                            </div>
                        </div>
                    </Deferred>
                </div>
            </div>
            <FlashMessage
                type="success"
                message={flashSuccess}
                onDismiss={() => setFlashSuccess(null)}
            />
        </Master>
    );
}