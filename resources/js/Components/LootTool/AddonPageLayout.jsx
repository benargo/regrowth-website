import LootToolNav from "@/Components/LootTool/LootToolNav";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import Master from "@/Layouts/Master";

/**
 * The frame of an addon page: the masthead (themed with `gameVersion`'s colour and banner
 * when given) and the loot tool's officer tool bar.
 */
export default function AddonPageLayout({ title, gameVersion = null, children }) {
    return (
        <Master title={title}>
            <SharedHeader
                title={title}
                backgroundClass="bg-officer-meeting"
                color={gameVersion?.color}
                image={gameVersion?.banner_url}
            />
            <LootToolNav />
            <PageContainer>{children}</PageContainer>
        </Master>
    );
}
