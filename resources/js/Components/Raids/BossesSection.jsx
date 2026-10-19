import { useMemo } from "react";
import { Link } from "@inertiajs/react";
import AutosavedSortableList from "@/Components/Datasets/AutosavedSortableList";
import { focusRing } from "@/Components/Datasets/RecordCard";
import Relationships from "@/Components/Datasets/Relationships";
import Icon from "@/Components/FontAwesome/Icon";
import { PrimaryButton, linkClassName } from "@/Components/FormControls";

/**
 * The raid's bosses in the order they're fought. Dropping a boss saves the
 * order straight away as `boss_ids`. Each boss links to its Edit page, and an
 * "Add boss" link opens the boss Create page for this raid; the Add boss link
 * returns to whichever raid page opened it. Outside autosave (the setup wizard),
 * a button moves on through `onSaved`.
 */
export default function BossesSection({ raid, routes, submitLabel = "Save and continue", onSaved, autosave = false }) {
    const bosses = useMemo(() => raid.bosses ?? [], [raid.bosses]);

    return (
        <Relationships
            id="bosses"
            title="Bosses"
            description="Put the bosses in the order they're fought. The order saves as soon as you drop a boss. Bosses are added, renamed and deleted on their own pages."
        >
            {bosses.length === 0 ? (
                <p className="text-gray-300">No bosses in this raid yet.</p>
            ) : (
                <AutosavedSortableList
                    items={bosses}
                    url={routes.update}
                    field="boss_ids"
                    saveKey={`raid-${raid.id}-boss-order`}
                    label={`${raid.name} bosses in the order they're fought`}
                    className="divide-y divide-amber-600/40 rounded border border-amber-600/40"
                    renderItem={(boss, { index, dragHandle }) => (
                        <div className="bg-brown-900 flex items-center gap-3 px-3 py-2">
                            {dragHandle}
                            <span className="w-6 shrink-0 text-right text-sm text-gray-300">{index + 1}.</span>
                            <Link
                                href={route("management.bosses.edit", boss.id)}
                                className={`rounded text-white underline hover:text-amber-200 ${focusRing}`}
                            >
                                {boss.name}
                            </Link>
                        </div>
                    )}
                />
            )}

            <p className="text-sm">
                <Link
                    href={route("management.bosses.create", {
                        raid: raid.id,
                        from: autosave ? "raid-edit" : "raid-setup",
                    })}
                    className={linkClassName}
                >
                    <Icon icon="plus" className="mr-1" />
                    Add boss<span className="sr-only"> to {raid.name}</span>
                </Link>
            </p>

            {!autosave && onSaved && (
                <div>
                    <PrimaryButton onClick={onSaved}>{submitLabel}</PrimaryButton>
                </div>
            )}
        </Relationships>
    );
}
