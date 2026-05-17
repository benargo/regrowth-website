import { useEchoPresence } from "@laravel/echo-react";

export default function EditorPresence({ eventId }) {
    const { users } = useEchoPresence(`event.${eventId}.editors`);

    if (!users || users.length === 0) {
        return null;
    }

    return (
        <div className="flex items-center" title="Currently editing">
            <div className="flex -space-x-2">
                {users.map((user) => (
                    <Avatar key={user.id} user={user} />
                ))}
            </div>
        </div>
    );
}

function Avatar({ user }) {
    return (
        <div className="group relative">
            <img
                src={user.avatar_url}
                alt={user.name}
                className="h-8 w-8 rounded-full border-2 border-brown-900 object-cover"
                onError={(e) => {
                    e.currentTarget.style.display = "none";
                    e.currentTarget.nextSibling.style.display = "flex";
                }}
            />
            <span
                className="hidden h-8 w-8 items-center justify-center rounded-full border-2 border-brown-900 bg-amber-700 text-xs font-semibold uppercase text-white"
                aria-hidden="true"
            >
                {user.name.charAt(0)}
            </span>
            <div className="pointer-events-none absolute bottom-full left-1/2 mb-1 -translate-x-1/2 whitespace-nowrap rounded bg-brown-900 px-2 py-0.5 text-xs text-white opacity-0 shadow transition-opacity group-hover:opacity-100">
                {user.name}
            </div>
        </div>
    );
}
