import AddonTabNav from "@/Components/LootTool/AddonTabNav";
import LootToolNav from "@/Components/LootTool/LootToolNav";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";

/**
 * The frame of an addon page: the masthead (themed with `gameVersion`'s colour and banner
 * when given), the loot tool's officer tool bar, and the addon tabs with `active` current.
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
            <LootToolNav />
            <PageContainer>
                <div className="mx-auto max-w-5xl">
                    <AddonTabNav active={active} />
                    {children}
                </div>
            </PageContainer>
        </Master>
    );
}
