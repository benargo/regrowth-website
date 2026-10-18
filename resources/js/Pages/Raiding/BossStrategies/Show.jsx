import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import Icon from "@/Components/FontAwesome/Icon";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import BossStrategy from "@/Components/Bosses/BossStrategy";

export default function ShowBossStrategy({ boss }) {
    boss = boss.data ?? boss ?? {};

    return (
        <Master title={boss.name}>
            <SharedHeader
                title={boss.name}
                image={boss.raid.banner_url}
                imagePosition={boss.raid.banner_position}
                color={boss.raid.color}
                backgroundClass="bg-officer-meeting"
            />
            <ToolNav>
                <ToolNavLink href={route("raiding.boss-strategies.index")}>
                    <Icon icon="arrow-left" style="solid" className="mr-2" />
                    <span>Back to {boss.raid.name} strategies</span>
                </ToolNavLink>
            </ToolNav>
            <PageContainer>
                <div className="grid grid-cols-2">
                    <BossStrategy boss={boss} />
                </div>
            </PageContainer>
        </Master>
    );
}
