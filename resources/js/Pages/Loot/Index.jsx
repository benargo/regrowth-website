import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import RaidCard from "@/Components/Loot/RaidCard";

export default function Index({ raids }) {
    return (
        <Master title="Loot Bias">
            <SharedHeader backgroundClass="bg-ssctk" title="Loot Bias" />
            <PageContainer>
                {/* Desktop grid */}
                <div className="hidden gap-4 sm:grid sm:grid-cols-2 lg:grid-cols-3">
                    {raids.map((raid) => (
                        <RaidCard key={raid.id} raid={raid} />
                    ))}
                </div>

                {/* Mobile scroll carousel */}
                <div className="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 sm:hidden">
                    {raids.map((raid) => (
                        <div key={raid.id} className="w-[80vw] flex-none snap-start">
                            <RaidCard raid={raid} />
                        </div>
                    ))}
                </div>
            </PageContainer>
        </Master>
    );
}
