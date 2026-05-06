import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import Collapsible from "@/Components/Collapsible";
import SharedHeader from "@/Components/SharedHeader";

export default function BossStrategiesIndex({ bosses, phases }) {
    bosses = bosses.data ?? bosses ?? [];
    phases = phases.data ?? phases ?? [];
    return (
        <Master title="Boss Strategies">
            <SharedHeader title="Boss Strategies" backgroundClass="bg-karazhan" />
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">
                    {phases?.map((phase) => (
                        <Collapsible
                            key={phase.id}
                            sessionKey={`boss_strategies:phase_${phase.number}`}
                            title={`Phase ${phase.number}`}
                            className="my-4 border-amber-600"
                            headerClassName="hover:bg-amber-600/10"
                            bodyClassName="border-amber-600"
                        >
                            <div className="flex flex-wrap gap-4">
                                {Object.values(phase.raids).map((raid) => (
                                    <div
                                        key={raid.id}
                                        className="flex min-w-[200px] flex-1 flex-col rounded border border-amber-600/40 bg-amber-600/5 p-4"
                                    >
                                        <h3 className="mb-4 text-lg font-bold text-white">{raid.name}</h3>
                                        <div className="flex flex-col gap-2">
                                            {bosses[String(raid.id)]?.map((boss) => (
                                                <Link
                                                    key={boss.id}
                                                    href={route("dashboard.boss-strategies.edit", {
                                                        boss: boss.id,
                                                        slug: boss.slug,
                                                    })}
                                                    className="w-full rounded border border-amber-600 bg-amber-600/20 px-3 py-2 text-center text-white transition-colors hover:bg-amber-600/40"
                                                >
                                                    {boss.name}
                                                </Link>
                                            ))}
                                        </div>
                                    </div>
                                ))}
                            </div>
                        </Collapsible>
                    ))}
                </div>
            </div>
        </Master>
    );
}
