import Master from '@/Layouts/Master';
import SharedHeader from '@/Components/SharedHeader';
import PageContainer from '@/Components/PageContainer';
import RaidCard from '@/Components/Loot/RaidCard';

export default function Index({ phases }) {
    return (
        <Master title="Loot Bias">
            <SharedHeader backgroundClass="bg-ssctk" title="Loot Bias" />
            <PageContainer>
                <div className="space-y-12">
                    {phases.map((phase) => (
                        <section key={phase.id}>
                            <div className="mb-4">
                                <h2 className="text-2xl font-bold text-amber-400">{phase.name}</h2>
                                {phase.description && (
                                    <p className="mt-1 text-sm text-gray-300">{phase.description}</p>
                                )}
                            </div>

                            {/* Desktop grid */}
                            <div className="hidden gap-4 sm:grid sm:grid-cols-2 lg:grid-cols-3">
                                {phase.raids.map((raid) => (
                                    <div key={raid.id} className={!phase.has_started ? 'opacity-50' : ''}>
                                        <RaidCard raid={raid} />
                                    </div>
                                ))}
                            </div>

                            {/* Mobile scroll carousel */}
                            <div className="flex snap-x snap-mandatory gap-4 overflow-x-auto pb-2 sm:hidden">
                                {phase.raids.map((raid) => (
                                    <div
                                        key={raid.id}
                                        className={`w-[80vw] flex-none snap-start ${!phase.has_started ? 'opacity-50' : ''}`}
                                    >
                                        <RaidCard raid={raid} />
                                    </div>
                                ))}
                            </div>

                            {!phase.has_started && (
                                <p className="mt-2 text-xs text-amber-600/70">This phase has not yet started.</p>
                            )}
                        </section>
                    ))}
                </div>
            </PageContainer>
        </Master>
    );
}
