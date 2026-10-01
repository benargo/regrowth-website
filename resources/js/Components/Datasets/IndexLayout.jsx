import { Link } from "@inertiajs/react";
import { focusRing } from "@/Components/Datasets/RecordCard";
import EmptyState from "@/Components/EmptyState";
import Icon from "@/Components/FontAwesome/Icon";
import PageContainer from "@/Components/PageContainer";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";

/**
 * A dataset's Index page body, below the page header: a link back to the
 * officers' dashboard, the intro beside an Add button, then the record cards
 * (`children`) in a grid, or an empty state when there are none.
 */
export default function IndexLayout({ intro, addHref, addLabel, isEmpty, emptyIcon, emptyMessage, children }) {
    return (
        <>
            <ToolNav>
                <div className="flex-initial space-x-4">
                    <ToolNavLink href={route("management.dashboard")} className={focusRing}>
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to officers' dashboard
                    </ToolNavLink>
                </div>
            </ToolNav>

            <PageContainer>
                <div className="mb-8 flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                    <p className="text-secondary-300">{intro}</p>
                    <Link
                        href={addHref}
                        className={`bg-ink-800 hover:bg-ink-900 inline-flex items-center justify-center gap-2 rounded px-4 py-2 text-sm font-semibold text-white ${focusRing}`}
                    >
                        <Icon icon="plus" style="light" />
                        {addLabel}
                    </Link>
                </div>

                {isEmpty ? (
                    <EmptyState icon={emptyIcon} message={emptyMessage} />
                ) : (
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">{children}</div>
                )}
            </PageContainer>
        </>
    );
}
