import Pill from "@/Components/Pill";

/** A pill naming a playable class, tinted in that class's colour. */
export default function ClassPill({ playableClass }) {
    if (!playableClass?.slug) {
        return null;
    }

    return (
        <Pill
            bgColor={`bg-playable-class-${playableClass.slug}/15`}
            textColor={`text-playable-class-${playableClass.slug}`}
        >
            {playableClass.name}
        </Pill>
    );
}
