import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import ItemRow from "@/Components/Loot/ItemRow";
import Pagination from "@/Components/Pagination";

export default function Index({ results, q }) {
    const items = results.data ?? [];

    return (
        <Master title={`Search: ${q}`}>
            <SharedHeader backgroundClass="bg-ssctk" title="Search" />
            <PageContainer>
                <p className="mb-4 text-sm text-gray-400">
                    {results.meta.total} {results.meta.total === 1 ? "result" : "results"} for &ldquo;{q}&rdquo;
                </p>

                {items.length === 0 ? (
                    <p className="text-gray-500 italic">No items found.</p>
                ) : (
                    <div className="space-y-2">
                        {items.map((item) => (
                            <ItemRow key={item.id} item={item} />
                        ))}
                    </div>
                )}

                <Pagination links={results.meta.links} meta={results.meta} itemName="items" />
            </PageContainer>
        </Master>
    );
}
