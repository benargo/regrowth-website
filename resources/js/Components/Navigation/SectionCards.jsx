import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import { SITE_SECTIONS } from "@/Helpers/siteSections";
import { usePermissionCheck } from "@/Hooks/usePermission";

/**
 * A hub page's cards: one per hub item of the section that the viewer may open.
 */
export default function SectionCards({ section }) {
    const hasPermission = usePermissionCheck();
    const cards = SITE_SECTIONS[section].items.filter(
        (item) => item.isHubCard && (!item.permission || hasPermission(item.permission)),
    );

    return (
        <nav aria-label={`${SITE_SECTIONS[section].label} pages`} className="mb-12">
            <ul className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                {cards.map((card) => (
                    <li key={card.key}>
                        <Link
                            href={route(card.routeName)}
                            className="flex h-full items-start gap-4 rounded border border-amber-600 p-4 transition-colors hover:bg-amber-600/20 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-300"
                        >
                            <Icon
                                icon={card.icon}
                                style="light"
                                className="text-3xl text-amber-400"
                                aria-hidden="true"
                            />
                            <span className="flex flex-col gap-1">
                                <span className="text-lg font-semibold text-white">{card.label}</span>
                                <span className="text-sm text-amber-100/80">{card.description}</span>
                            </span>
                        </Link>
                    </li>
                ))}
            </ul>
        </nav>
    );
}
