import AddonTabNav from "@/Components/LootTool/AddonTabNav";
import Icon from "@/Components/FontAwesome/Icon";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Master from "@/Layouts/Master";

/**
 * The frame of an addon page: the masthead (themed with `gameVersion`'s colour and banner
 * when given), a link back to the officers' dashboard, and the addon tabs with `active` current.
 */
export default function AddonPageLayout({ title, active, gameVersion = null, children }) {
    return (
        <Master title={title}>
            <SharedHeader
                title={title}
                backgroundClass="bg-officer-meeting"
                color={gameVersion?.color}
                image={gameVersion?.banner_url}
            />
            <ToolNav>
                <ToolNavLink href={route("management.dashboard")}>
                    <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                    Back to officers' dashboard
                </ToolNavLink>
            </ToolNav>
            <PageContainer>
                <div className="mx-auto max-w-5xl">
                    <AddonTabNav active={active} />
                    {children}
                </div>
            </PageContainer>
        </Master>
    );
}
