import Master from "@/Layouts/Master";
import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import SharedHeader from "@/Components/SharedHeader";

function headerInner() {
    return (
        <div className="flex flex-col items-center justify-center gap-2">
            <p className="text-amber-400 text-sm font-semibold uppercase tracking-widest">
                Error 410
            </p>
            <div className="flex flex-row items-center gap-4">
                <Icon icon="dragon" style="solid" className="text-4xl text-amber-500" />
                <span className="text-4xl font-bold">Raid Plan Gone</span>
            </div>
        </div>
    );
}

export default function Gone() {
    return (
        <Master title="Raid Plan Gone">
            <SharedHeader title={headerInner()} />

            <div className="py-16 text-white">
                <div className="container mx-auto px-4 max-w-2xl text-center">
                    <div className="border border-amber-600/30 rounded-lg p-8 mb-8 bg-black/20">
                        <p className="text-gray-300 text-lg leading-relaxed mb-4">
                            This raid plan existed once, but has since been removed from our records.
                            Old raid plans are pruned automatically after one month.
                        </p>
                        <p className="text-gray-400 text-sm">
                            If you're looking for active plans, head back to the raiding schedule.
                        </p>
                    </div>

                    <Link
                        href={route("raiding.index")}
                        className="inline-flex items-center gap-2 bg-amber-600 hover:bg-amber-700 text-white font-semibold px-6 py-3 rounded transition-colors"
                    >
                        <Icon icon="dragon" style="solid" />
                        Back to Raiding
                    </Link>
                </div>
            </div>
        </Master>
    );
}
