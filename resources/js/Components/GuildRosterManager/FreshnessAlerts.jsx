import { Link } from "@inertiajs/react";
import Alert from "@/Components/Alert";
import Icon from "@/Components/FontAwesome/Icon";

const LINK_CLASSES = {
    error: "bg-red-600 hover:bg-red-800 focus:ring-red-500",
    warning: "bg-yellow-600 hover:bg-yellow-800 focus:ring-yellow-500",
};

function UploadLink({ gameVersion, className }) {
    return (
        <Link
            href={route("management.grm.create", { game_version: gameVersion.slug })}
            className={`inline-flex items-center rounded-md border border-transparent p-4 text-sm font-semibold text-white transition duration-150 ease-in-out focus:ring-2 focus:ring-offset-2 focus:outline-hidden disabled:opacity-25 ${className}`}
        >
            <Icon icon="file-upload" style="solid" className="mr-2" />
            <span className="whitespace-nowrap">Upload GRM Data</span>
        </Link>
    );
}

function FreshnessAlert({ gameVersion, type, title, children }) {
    return (
        <div className="mb-6 md:mx-20">
            <Alert type={type}>
                <div className="flex flex-col items-center gap-2 md:flex-row">
                    <div className="flex-auto">
                        <h2 className="mb-1 text-lg font-bold">{title}</h2>
                        <p>{children}</p>
                    </div>
                    <div className="flex-initial">
                        <UploadLink gameVersion={gameVersion} className={LINK_CLASSES[type]} />
                    </div>
                </div>
            </Alert>
        </div>
    );
}

/**
 * One alert per game version whose GRM upload is missing raiders, missing entirely,
 * or outdated. Versions with fresh uploads render nothing.
 */
export default function FreshnessAlerts({ freshness = [] }) {
    return freshness.map(({ gameVersion, lastModified, dataIsStale, dataIsOutdated }) => {
        // Checked before staleness: a missing upload counts zero raiders, so it is
        // also flagged stale whenever the live roster has a few raiders.
        if (lastModified === null) {
            return (
                <FreshnessAlert
                    key={gameVersion.id}
                    gameVersion={gameVersion}
                    type="warning"
                    title={`No GRM data for ${gameVersion.title}`}
                >
                    No GRM data has been uploaded for {gameVersion.title} yet. Please upload a GRM export to ensure your
                    addon data is up to date.
                </FreshnessAlert>
            );
        }

        if (dataIsStale) {
            return (
                <FreshnessAlert
                    key={gameVersion.id}
                    gameVersion={gameVersion}
                    type="error"
                    title={`GRM data out of date for ${gameVersion.title}`}
                >
                    The {gameVersion.title} GRM data used to generate this addon data is missing raiders. Please
                    consider uploading a fresh GRM export to ensure your addon data is up to date.
                </FreshnessAlert>
            );
        }

        if (dataIsOutdated) {
            return (
                <FreshnessAlert
                    key={gameVersion.id}
                    gameVersion={gameVersion}
                    type="warning"
                    title={`Old GRM data detected for ${gameVersion.title}`}
                >
                    The {gameVersion.title} GRM data used to generate this addon data was last updated on{" "}
                    {new Date(lastModified).toLocaleDateString()}. Please consider uploading a fresh GRM export to
                    ensure your addon data is up to date.
                </FreshnessAlert>
            );
        }

        return null;
    });
}
