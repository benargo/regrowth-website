import { useRef, useState } from "react";
import Master from "@/Layouts/Master";
import FlashMessage from "@/Components/FlashMessage";
import Pill from "@/Components/Pill";
import Icon from "@/Components/FontAwesome/Icon";
import SharedHeader from "@/Components/SharedHeader";
import TabNav from "@/Components/TabNav";

export default function AddonExportSchema({ schema }) {
    const [flashSuccess, setFlashSuccess] = useState(null);
    const dataRef = useRef(null);
    const schemaJson = JSON.stringify(schema, null, 2);

    function selectAllContent() {
        if (dataRef.current) {
            const selection = window.getSelection();
            const range = document.createRange();
            range.selectNodeContents(dataRef.current);
            selection.removeAllRanges();
            selection.addRange(range);
        }
    }

    function copySchema() {
        navigator.clipboard.writeText(schemaJson).then(() => {
            setFlashSuccess("Schema copied to clipboard!");
        });
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
                        currentTab="schema"
                    />
                    <div className="flex flex-row items-baseline space-x-4">
                        <p className="flex-1">This is the JSON schema for the addon export data format.</p>
                        <button
                            className="flex flex-none items-center justify-center rounded bg-blue-600 px-4 py-2 font-bold text-white hover:bg-blue-800"
                            onClick={copySchema}
                        >
                            <Icon icon="copy" style="solid" className="mr-2" />
                            <span>Copy Schema</span>
                        </button>
                    </div>
                    <div className="mt-6">
                        <pre
                            ref={dataRef}
                            onClick={selectAllContent}
                            className="max-h-[600px] min-h-64 w-full cursor-pointer overflow-auto rounded border border-gray-800 bg-brown-800/50 p-4 text-sm text-white"
                        >
                            {schemaJson}
                        </pre>
                    </div>
                    <div className="mt-6 border-t border-amber-700 pt-6">
                        <h2 className="mb-4 text-2xl font-semibold">Changelog</h2>
                        <h3 className="text-md font-semibold">
                            Version 1.2.0 <span className="text-sm italic text-gray-400">(2026-02-04)</span>
                        </h3>
                        <ul className="mt-2 list-inside list-disc text-sm">
                            <li>
                                Added <code>councillors</code> array to include loot councillor data.
                            </li>
                        </ul>
                        <h3 className="text-md font-semibold">
                            Version 1.1.2 <span className="text-sm italic text-gray-400">(2026-02-03)</span>
                        </h3>
                        <ul className="mt-2 list-inside list-disc text-sm">
                            <li>
                                <code>system.last_modified</code> now returns an Epoch timestamp. Previously it returned
                                an ISO 8601 string.
                            </li>
                        </ul>
                        <h3 className="text-md mt-4 font-semibold">
                            Version 1.1.1 <span className="text-sm italic text-gray-400">(2026-02-02)</span>
                        </h3>
                        <ul className="mt-2 list-inside list-disc text-sm">
                            <li>
                                Added <code>roleId</code> to <code>members</code> objects.
                            </li>
                        </ul>
                        <h3 className="text-md mt-4 font-semibold">
                            Version 1.1.0 <span className="text-sm italic text-gray-400">(2026-02-01)</span>
                        </h3>
                        <ul className="mt-2 list-inside list-disc text-sm">
                            <li>
                                Added <code>members</code> array to include guild member data.
                            </li>
                            <li>
                                Added <code>rankId</code> to <code>members</code> objects.
                            </li>
                        </ul>
                        <h3 className="mt-4 flex flex-row items-center gap-2 font-semibold">
                            <span className="text-lg">Version 1.0.0 </span>
                            <span className="text-sm italic text-gray-400">(2026-01-31)</span>
                            <Pill bgColor="bg-blue-700">Major release</Pill>
                        </h3>
                        <ul className="mt-2 list-inside list-disc text-sm">
                            <li>Initial version of the addon export schema.</li>
                        </ul>
                    </div>
                </div>
            </div>
            <FlashMessage type="success" message={flashSuccess} onDismiss={() => setFlashSuccess(null)} />
        </Master>
    );
}
