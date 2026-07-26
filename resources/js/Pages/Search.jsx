import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import PageContainer from "@/Components/PageContainer";
import ItemRow from "@/Components/Loot/ItemRow";
import Pagination from "@/Components/Pagination";

export default function Index({ results, q, scoped_raid }) {
    const items = results.data ?? [];
    const meta = results.meta ?? {};

    return (
        <Master title={`Search results for "${q}"`}>
            <SharedHeader backgroundClass="bg-ssctk" title="Search results" />
            <PageContainer>
                <div className="mb-4 flex items-center gap-3">
                    <p className="text-sm text-gray-400">
                        {meta.total} {meta.total === 1 ? "result" : "results"} for &ldquo;{q}&rdquo;
                    </p>
                    {scoped_raid?.data && (
                        <span className="inline-flex items-center gap-1 rounded bg-amber-600/20 px-2 py-1 text-xs font-semibold text-amber-500">
                            {scoped_raid.data.name}
                        </span>
                    )}
                </div>

                {items.length === 0 ? (
                    <p className="text-gray-500 italic">No items found.</p>
                ) : (
                    <div className="space-y-2">
                        {items.map((item) => (
                            <ItemRow key={item.id} item={item} />
                        ))}
                    </div>
                )}

                <Pagination links={meta.links} meta={meta} itemName="items" />
            </PageContainer>
        </Master>
    );
}
