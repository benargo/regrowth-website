import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import RaidCard from "@/Components/Loot/RaidCard";

export default function Index({ raids }) {
    return (
        <Master title="Loot Bias">
            <SharedHeader backgroundClass="bg-ssctk" title="Loot Bias" />
            <PageContainer>
                <div className="gap-4 grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3">
                    {raids.map((raid) => (
                        <RaidCard key={raid.id} raid={raid} />
                    ))}
                </div>
            </PageContainer>
        </Master>
    );
}
