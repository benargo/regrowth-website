const medalStyles = {
    0: {
        bg: 'bg-yellow-600',
        border: 'border-yellow-500',
        text: 'text-yellow-900',
        label: 'Gold',
    },
    1: {
        bg: 'bg-gray-500',
        border: 'border-gray-400',
        text: 'text-gray-900',
        label: 'Silver',
    },
    2: {
        bg: 'bg-amber-800',
        border: 'border-amber-700',
        text: 'text-amber-100',
        label: 'Bronze',
    },
};

export default function LevelRace({ members }) {
    const levels = Object.keys(members).sort((a, b) => b - a);

    return (
        <div className="py-12">
            <div className="mx-auto max-w-6xl px-4">
                <h2 className="mb-8 text-center text-3xl font-bold text-white">The Race to Level 70</h2>
                <div className="grid gap-6 md:grid-cols-3">
                    {levels.map((level, index) => {
                        const style = medalStyles[index] || medalStyles[2];
                        const groupMembers = members[level];

                        return (
                            <div
                                key={level}
                                className={`rounded-lg border-2 ${style.bg}/50 ${style.border}  overflow-hidden`}
                            >
                                <div className={`${style.bg} ${style.text} px-4 py-3`}>
                                    <div className="flex items-center justify-between">
                                        <span className="text-lg font-semibold">{style.label}</span>
                                        <span className="text-2xl font-bold">Level {level}</span>
                                    </div>
                                </div>
                                <div className=" p-4">
                                    <ul className="space-y-2">
                                        {groupMembers.map((member) => (
                                            <li
                                                key={member.character.id}
                                                className="flex items-center gap-2 text-gray-200"
                                            >
                                                <span className="font-medium">
                                                    {member.character.name}
                                                </span>
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            </div>
                        );
                    })}
                </div>
            </div>
        </div>
    );
}
