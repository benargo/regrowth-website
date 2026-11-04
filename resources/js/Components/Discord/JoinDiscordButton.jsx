import Icon from "@/Components/FontAwesome/Icon";

/**
 * A large Discord-branded button linking to the guild's Discord, opening in a
 * new tab.
 */
export default function JoinDiscordButton() {
    return (
        <a
            href={route("discord")}
            target="_blank"
            rel="nofollow noopener noreferrer"
            className="bg-discord border-discord/30 inline-flex items-center gap-3 rounded border px-8 py-4 text-lg text-white shadow-lg transition-[filter,box-shadow] hover:shadow-xl hover:brightness-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
        >
            <Icon icon="discord" style="brands" className="h-6 w-6" />
            Join Our Discord
        </a>
    );
}
