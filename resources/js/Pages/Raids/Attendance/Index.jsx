import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";

export default function Index() {
    return (
        <Master title="Attendance Dashboard">
            <SharedHeader title="Attendance Dashboard" backgroundClass="bg-illidan" />
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    <div className="flex items-start gap-4">
                        <Link
                            href={route("raids.attendance.matrix")}
                            className="flex w-full items-center gap-4 rounded border border-amber-600 p-4 transition-colors hover:bg-amber-600/20 lg:w-1/3"
                        >
                            <h2 className="mb-2 text-2xl font-semibold">Matrix</h2>
                        </Link>
                    </div>
                </div>
            </div>
        </Master>
    );
}
