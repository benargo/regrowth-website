import Icon from "@/Components/FontAwesome/Icon";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import cn from "@/Helpers/cn";

const PAGES = [
    { routeName: "management.grm.create", label: "Upload", icon: "file-upload" },
    { routeName: "management.grm.sync", label: "Sync dashboard", icon: "clipboard-list-check" },
];

/**
 * The tool bar shared by the GRM pages: a link back to the officers' dashboard and a link to each GRM page,
 * with the current one marked.
 */
export default function GrmToolNav({ current }) {
    return (
        <ToolNav aria-label="GRM tools">
            <ToolNavLink href={route("management.dashboard")}>
                <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" aria-hidden="true" />
                Back to officers’ dashboard
            </ToolNavLink>
            <div className="flex flex-row flex-wrap gap-1" role="group" aria-label="GRM pages">
                {PAGES.map((page) => (
                    <ToolNavLink
                        key={page.routeName}
                        href={route(page.routeName)}
                        aria-current={page.routeName === current ? "page" : undefined}
                        className={cn(page.routeName === current && "bg-brown-800 border-amber-600 font-semibold")}
                    >
                        <Icon icon={page.icon} style="solid" className="mr-2 text-xs" aria-hidden="true" />
                        {page.label}
                    </ToolNavLink>
                ))}
            </div>
        </ToolNav>
    );
}
