import { router } from "@inertiajs/react";
import { useState } from "react";
import AddonPageLayout from "@/Components/LootTool/AddonPageLayout";
import GameVersionExport from "@/Components/LootTool/GameVersionExport";

const UNICODE_ESCAPE = /\\u([0-9a-fA-F]{4})/g;

const FORMATS = {
    json: {
        label: "JSON",
        description: "The addon data as formatted JSON. Click the data to select all of it.",
        copyLabel: "Copy JSON Data",
        copySuccessMessage: "JSON data copied to clipboard!",
        display: (exportedData) =>
            exportedData.replace(UNICODE_ESCAPE, (_, hex) => String.fromCharCode(parseInt(hex, 16))),
    },
    base64: {
        label: "Base64",
        description:
            "This is the version you should import into the addon. Click the button to copy it to your clipboard.",
        copyLabel: "Copy Addon Data",
        copySuccessMessage: "Addon data copied to clipboard!",
        wrap: true,
    },
};

export default function AddonExport({ gameVersion: gameVersionResource, hasExport, json, base64, grmFreshness }) {
    const gameVersion = gameVersionResource?.data ?? null;
    const [selectedFormat, setSelectedFormat] = useState("json");

    function selectFormat(format) {
        setSelectedFormat(format);

        if (format === "base64" && base64 === undefined) {
            router.reload({ only: ["base64"] });
        }
    }

    return (
        <AddonPageLayout title="Export Addon Data" gameVersion={gameVersion}>
            <GameVersionExport
                gameVersion={gameVersion}
                hasExport={hasExport}
                exports={{ json, base64 }}
                grmFreshness={grmFreshness}
                formats={FORMATS}
                selectedFormat={selectedFormat}
                onSelectFormat={selectFormat}
            />
        </AddonPageLayout>
    );
}
