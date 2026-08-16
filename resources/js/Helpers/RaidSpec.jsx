export default function raidSpec(character) {
    return Array.isArray(character.specializations)
        ? (character.specializations.find((s) => s.is_raid_spec) ?? null)
        : null;
}
