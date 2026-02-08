const podiumData = [
    {
        rank: 1,
        label: '1st',
        names: ['Marktführer'],
        bg: 'bg-yellow-600',
        border: 'border-yellow-500',
        text: 'text-yellow-900',
        height: 'h-32 md:h-40',
        order: 'order-1 md:order-2',
    },
    {
        rank: 2,
        label: '2nd',
        names: ['Yaj'],
        bg: 'bg-gray-500',
        border: 'border-gray-400',
        text: 'text-gray-900',
        height: 'h-24 md:h-28',
        order: 'order-2 md:order-1',
    },
    {
        rank: 3,
        label: '3rd',
        names: ['Izepo', 'Jsherratt'],
        bg: 'bg-amber-800',
        border: 'border-amber-700',
        text: 'text-amber-100',
        height: 'h-20 md:h-20',
        order: 'order-3 md:order-3',
    },
];

export default function Podium() {
    return (
        <div className="py-12">
            <div className="mx-auto max-w-4xl px-4">
                <h2 className="mb-8 text-center text-3xl font-bold text-white">The Race to Level 70</h2>
                <div className="flex flex-col items-center gap-4 md:flex-row md:items-end md:justify-center md:gap-6">
                    {podiumData.map((place) => (
                        <div
                            key={place.rank}
                            className={`w-full max-w-xs ${place.order} flex flex-col md:w-64`}
                        >
                            <div className="mb-2 text-center">
                                {place.names.map((name, index) => (
                                    <span key={name} id={`podium-name-${name.toLowerCase()}`} className="text-lg font-semibold text-white">
                                        {name}
                                        {index < place.names.length - 1 && (
                                            <span className="mx-2 text-gray-400">&</span>
                                        )}
                                    </span>
                                ))}
                            </div>
                            <div
                                className={`${place.bg} ${place.border} ${place.height} flex items-center justify-center rounded-t-lg border-2 border-b-0`}
                            >
                                <span className={`text-3xl font-bold ${place.text}`}>
                                    {place.label}
                                </span>
                            </div>
                        </div>
                    ))}
                </div>
            </div>
        </div>
    );
}
