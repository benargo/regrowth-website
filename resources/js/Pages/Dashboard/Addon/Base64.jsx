import { useRef, useState } from 'react';
import Master from '@/Layouts/Master';
import SharedHeader from '@/Components/SharedHeader';
import FlashMessage from '@/Components/FlashMessage';
import TabNav from '@/Components/TabNav';

export default function AddonExport({ exportedData }) {
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
                    <div className="mt-6">
                        <div
                            ref={dataRef}
                            onClick={selectAllContent}
                            className="w-full min-h-64 bg-brown-800/50 border border-gray-800 text-white p-4 rounded break-all cursor-pointer"
                        >
                            {exportedData.length === 0 && (<p>No addon data available.</p>)}
                            {exportedData}
                        </div>
                    </div>
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