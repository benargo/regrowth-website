import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import cn from "@/Helpers/cn";

/**
 * A breadcrumb trail. Every crumb but the last links to its `href`; the last is the current page.
 */
export default function Breadcrumbs({ crumbs, className = "mb-6" }) {
    return (
        <nav aria-label="Breadcrumb" className={className}>
            <ol className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                {crumbs.map((crumb, index) => {
                    const isCurrent = index === crumbs.length - 1;

                    return (
                        <li key={`${index}-${crumb.label}`} className="flex items-center gap-2">
                            <Icon
                                icon="chevron-right"
                                style="solid"
                                className={cn("text-xs text-amber-600", index === 0 && "hidden")}
                                aria-hidden="true"
                            />
                            {isCurrent ? (
                                <span aria-current="page" className="font-semibold text-white">
                                    {crumb.label}
                                </span>
                            ) : (
                                <Link
                                    href={crumb.href}
                                    className={cn(
                                        "inline-flex min-h-6 items-center text-amber-200 underline-offset-4 hover:text-amber-100 hover:underline",
                                        "focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400",
                                    )}
                                >
                                    {crumb.label}
                                </Link>
                            )}
                        </li>
                    );
                })}
            </ol>
        </nav>
    );
}
