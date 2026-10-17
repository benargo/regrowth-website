import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import FormattedMarkdown from "@/Components/FormattedMarkdown";
import { Can } from "@/Components/Authorizable";

export default function BossStrategy({ boss }) {
    if (!(boss.images?.length > 0 || boss.notes)) {
        return (
            <div className="col-span-2 flex items-center justify-center gap-4 text-center">
                <p className="text-center text-sm text-gray-500">
                    No strategy notes or images for this boss yet.
                </p>
            </div>
        );
    }

    return (
        <div className="col-span-2 flex flex-col gap-4">
            <div className="flex flex-row items-start gap-2">
                <h2 className="flex-1 text-lg font-semibold text-amber-500">Strategy</h2>
                <Can permission="manage-boss-strategies">
                    <Link
                        href={route("management.boss-strategies.edit", {
                            boss: boss.id,
                            slug: boss.slug,
                        })}
                        className="inline-flex items-center gap-2 rounded border border-amber-600 px-4 py-2 text-sm text-gray-200 transition-colors hover:bg-amber-600/20"
                    >
                        <Icon icon="pencil" className="text-sm" />
                        Edit boss strategy
                    </Link>
                </Can>
            </div>
            {boss.images?.length > 0 &&
                boss.images.map((url, i) => (
                    <div
                        key={`${boss.id}_image_${i}`}
                        className="flex items-center justify-center gap-4 text-center"
                    >
                        <img
                            src={url}
                            alt={`${boss.name} strategy ${i + 1}`}
                            className="rounded-lg border border-amber-600/30"
                        />
                    </div>
                ))}
            {boss.notes && <FormattedMarkdown>{boss.notes}</FormattedMarkdown>}
        </div>
    );
}
