import Icon from "@/Components/FontAwesome/Icon";

/**
 * A character's Blizzard portrait. Without one, it falls back to the Blizzard
 * shadow avatar for the character's race and gender, then to a dimmed class
 * icon, then to a generic user icon. Decorative by default, because the
 * character's name is almost always shown beside it.
 */
export default function CharacterPortrait({ character, className = "size-8", alt = "" }) {
    const portraitUrl = character.portrait_url ?? character.shadow_avatar_url;

    if (portraitUrl) {
        return (
            <img
                src={portraitUrl}
                alt={alt}
                className={`border-amber-600/30 shrink-0 rounded-[20%] border shadow-lg shadow-black/50 ${className}`}
            />
        );
    }

    return (
        <span
            aria-hidden={alt ? undefined : "true"}
            role={alt ? "img" : undefined}
            aria-label={alt || undefined}
            className={`border-gray-600/30 bg-gray-700/50 flex shrink-0 items-center justify-center rounded-[20%] border shadow-lg shadow-black/50 ${className}`}
        >
            {character.playable_class?.icon_url ? (
                <img src={character.playable_class.icon_url} alt="" className="size-1/2 rounded opacity-40" />
            ) : (
                <Icon icon="user" style="light" className="text-gray-400" />
            )}
        </span>
    );
}
