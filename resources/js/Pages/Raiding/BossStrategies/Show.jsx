import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import Icon from "@/Components/FontAwesome/Icon";
import BossStrategy from "@/Components/Bosses/BossStrategy";

export default function ShowBossStrategy({ boss }) {
    boss = boss.data ?? boss ?? {};

    return (
        <Master title={boss.name}>
            <SharedHeader title={boss.name} backgroundClass={boss.raid.background ?? "bg-officer-meeting"} />
            <nav className="bg-brown-900 shadow">
                <div className="container mx-auto max-w-4xl px-4">
                    <div className="flex min-h-12 flex-col items-center justify-between md:flex-row">
                        <div className="flex-initial space-x-4">
                            <Link
                                href={route("raiding.boss-strategies.index")}
                                className="my-2 flex flex-row items-center rounded-md border border-transparent p-2 text-sm font-medium text-white hover:border-primary hover:bg-brown-800 active:border-primary"
                            >
                                <Icon icon="arrow-left" style="solid" className="mr-2" />
                                <span>Back to {boss.raid.name} strategies</span>
                            </Link>
                        </div>
                    </div>
                </div>
            </nav>
            <PageContainer>
                <div className="grid grid-cols-2">
                    <BossStrategy boss={boss} />
                </div>
            </PageContainer>
        </Master>
    );
}
