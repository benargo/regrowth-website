import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import RaidCard from "@/Components/Loot/RaidCard";
import SearchBar from "@/Components/Search/SearchBar";

export default function Index({ raids }) {
    return (
        <Master title="Loot Bias">
            <SharedHeader backgroundClass="bg-ssctk" title="Loot Bias" />
            <PageContainer>
                <div className="mb-6 max-w-xl">
                    <SearchBar />
                </div>
                <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                    {raids.data.map((raid) => (
                        <RaidCard key={raid.id} raid={raid} />
                    ))}
                </div>
            </PageContainer>
        </Master>
    );
}
