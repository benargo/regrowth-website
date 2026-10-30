import ChapterForm from "@/Components/RaidInformation/ChapterForm";
import Icon from "@/Components/FontAwesome/Icon";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import ToolNav, { ToolNavLink } from "@/Components/ToolNav";
import Master from "@/Layouts/Master";

export default function Create({ mentions }) {
    return (
        <Master title="New chapter">
            <SharedHeader backgroundClass="bg-officer-meeting" title="New chapter" />

            <ToolNav>
                <div className="flex-initial space-x-4">
                    <ToolNavLink href={route("management.raid-information.index")}>
                        <Icon icon="arrow-left" style="solid" className="mr-1 text-xs" />
                        Back to raid information
                    </ToolNavLink>
                </div>
            </ToolNav>

            <PageContainer>
                <ChapterForm mentions={mentions} submitLabel="Add chapter" processingLabel="Adding…" />
            </PageContainer>
        </Master>
    );
}
