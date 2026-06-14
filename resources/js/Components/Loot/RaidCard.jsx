import { Link } from "@inertiajs/react";

export default function RaidCard({ raid }) {
    const backgroundClass = raid.background ?? "bg-brown-900";
    const borderColor = raid.color ? `#${raid.color}` : null;

    return (
        <Link
            href={route("loot.raids.show", { raid: raid.id, name: raid.slug })}
            className="group block overflow-hidden rounded-lg border border-amber-600/30 shadow-lg transition-all duration-300 hover:scale-[1.03]"
            style={borderColor ? { "--raid-color": borderColor } : undefined}
            onMouseEnter={(e) => borderColor && (e.currentTarget.style.borderColor = borderColor)}
            onMouseLeave={(e) => borderColor && (e.currentTarget.style.borderColor = "")}
        >
            <div className={`${backgroundClass} relative aspect-video w-full bg-cover bg-center`}>
                <div className="absolute inset-0 bg-linear-to-t from-black/80 via-black/30 to-transparent" />
                <div className="absolute bottom-0 left-0 right-0 p-4">
                    {raid.phase?.number && (
                        <p className="mb-1 text-xs font-semibold uppercase tracking-widest text-amber-400">
                            Phase {raid.phase.number}
                        </p>
                    )}
                    <h3 className="text-xl font-bold text-white drop-shadow-lg">{raid.name}</h3>
                </div>
            </div>
        </Link>
    );
}
