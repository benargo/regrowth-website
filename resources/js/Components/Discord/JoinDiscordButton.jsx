import DiscordButton from "@/Components/Discord/DiscordButton";

/**
 * A large Discord-branded button linking to the guild's Discord, opening in a
 * new tab.
 */
export default function JoinDiscordButton() {
    return (
        <DiscordButton href={route("discord")} target="_blank" rel="nofollow noopener noreferrer">
            Join Our Discord
        </DiscordButton>
    );
}
