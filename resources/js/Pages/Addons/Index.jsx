import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import SectionNav from "@/Components/Navigation/SectionNav";
import SectionCards from "@/Components/Navigation/SectionCards";
import { SITE_SECTIONS } from "@/Helpers/siteSections";

export default function Index() {
    return (
        <Master title="Regrowth Addons">
            <SharedHeader title="Regrowth Addons" backgroundClass={SITE_SECTIONS.addons.headerBackgroundClass} />
            <SectionNav section="addons" />
            <PageContainer>
                <p className="mb-8 max-w-prose text-lg">
                    In-game addons built for Regrowth raiders and the loot council.
                </p>

                <SectionCards section="addons" />
            </PageContainer>
        </Master>
    );
}
