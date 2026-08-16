import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import Icon from "@/Components/FontAwesome/Icon";
import ToolNav from "@/Components/ToolNav";
import BossStrategy from "@/Components/Bosses/BossStrategy";

export default function ShowBossStrategy({ boss }) {
    boss = boss.data ?? boss ?? {};

    return (
        <Master title={boss.name}>
            <SharedHeader title={boss.name} backgroundClass={boss.raid.background ?? "bg-officer-meeting"} />
            <ToolNav>
                <Link
                    href={route("raiding.boss-strategies.index")}
                    className="hover:border-primary hover:bg-brown-800 active:border-primary my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white"
                >
                    <Icon icon="arrow-left" style="solid" className="mr-2" />
                    <span>Back to {boss.raid.name} strategies</span>
                </Link>
            </ToolNav>
            <PageContainer>
                <div className="grid grid-cols-2">
                    <BossStrategy boss={boss} />
                </div>
            </PageContainer>
        </Master>
    );
}
