import AddonPageLayout from "@/Components/LootTool/AddonPageLayout";
import ExportPanel from "@/Components/LootTool/ExportPanel";
import { FormPageSection } from "@/Components/FormPage";
import Pill from "@/Components/Pill";

/**
 * One release in the schema changelog, as an entry on a vertical timeline.
 */
function ChangelogEntry({ version, date, isMajor = false, children }) {
    return (
        <li className="relative pb-8 pl-6 last:pb-0">
            <span
                aria-hidden="true"
                className={`absolute top-1.5 -left-[5px] size-2.5 rounded-full ${
                    isMajor ? "bg-amber-400" : "bg-amber-700"
                }`}
            />
            <h3 className="flex flex-wrap items-center gap-x-3 gap-y-1">
                <span className={isMajor ? "text-lg font-semibold text-white" : "font-semibold text-gray-100"}>
                    Version {version}
                </span>
                <time dateTime={date} className="text-sm text-gray-400 italic">
                    {date}
                </time>
                <span className={isMajor ? "" : "hidden"}>
                    <Pill bgColor="bg-blue-700">Major release</Pill>
                </span>
            </h3>
            <ul className="mt-2 list-outside list-disc space-y-1 pl-4 text-sm text-gray-200">{children}</ul>
        </li>
    );
}

export default function AddonExportSchema({ schema }) {
    const schemaJson = JSON.stringify(schema, null, 2);

    return (
        <AddonPageLayout title="Export Addon Data" active="schema">
            <ExportPanel
                title="Schema"
                description="This is the JSON schema for the addon export data format."
                copyValue={schemaJson}
                copyLabel="Copy Schema"
                copySuccessMessage="Schema copied to clipboard!"
            />

            <div className="mt-12">
                <FormPageSection id="changelog" title="Changelog">
                    <ol className="ml-1 border-l border-amber-700/70">
                        <ChangelogEntry version="2.0.0" date="2026-07-31" isMajor>
                            <li>
                                Added <code>phases</code> array to include start dates for each phase (where known).
                            </li>
                            <li>
                                <code>players.attendance.first_attendance</code> can now return <code>null</code> when a
                                player has no recorded attendance.
                            </li>
                            <li>
                                <code>phases.start_time</code> and <code>players.attendance.first_attendance</code> now
                                return Unix timestamps instead of ISO 8601 strings.
                            </li>
                        </ChangelogEntry>
                        <ChangelogEntry version="1.2.0" date="2026-02-04">
                            <li>
                                Added <code>councillors</code> array to include loot councillor data.
                            </li>
                        </ChangelogEntry>
                        <ChangelogEntry version="1.1.2" date="2026-02-03">
                            <li>
                                <code>system.last_modified</code> now returns an Epoch timestamp. Previously it returned
                                an ISO 8601 string.
                            </li>
                        </ChangelogEntry>
                        <ChangelogEntry version="1.1.1" date="2026-02-02">
                            <li>
                                Added <code>roleId</code> to <code>members</code> objects.
                            </li>
                        </ChangelogEntry>
                        <ChangelogEntry version="1.1.0" date="2026-02-01">
                            <li>
                                Added <code>members</code> array to include guild member data.
                            </li>
                            <li>
                                Added <code>rankId</code> to <code>members</code> objects.
                            </li>
                        </ChangelogEntry>
                        <ChangelogEntry version="1.0.0" date="2026-01-31" isMajor>
                            <li>Initial version of the addon export schema.</li>
                        </ChangelogEntry>
                    </ol>
                </FormPageSection>
            </div>
        </AddonPageLayout>
    );
}
