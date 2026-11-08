/**
 * The racers grouped by level, keeping the order given for both the groups and
 * the racers within each group.
 *
 * @returns {Array<{ level: number, racers: Array<object> }>}
 */
export default function groupByLevel(racers) {
    const groups = new Map();

    racers.forEach((racer) => {
        if (!groups.has(racer.level)) {
            groups.set(racer.level, []);
        }

        groups.get(racer.level).push(racer);
    });

    return [...groups].map(([level, levelRacers]) => ({ level, racers: levelRacers }));
}
