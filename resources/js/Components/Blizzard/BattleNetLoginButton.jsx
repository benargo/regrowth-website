import Icon from "@/Components/FontAwesome/Icon";

/**
 * Links to the Battle.net OAuth redirect. Uses a plain anchor rather than an
 * Inertia <Link> because the flow leaves the app for Blizzard's login page.
 */
export default function BattleNetLoginButton({ className = "", children = "Login with Battle.net", ...props }) {
    return (
        <a
            href={route("battlenet.redirect")}
            className={`inline-flex items-center gap-2 rounded-md bg-[#0074e0] px-4 py-2 text-white transition-colors hover:bg-[#005fb8] focus:ring-2 focus:ring-[#0074e0]/60 focus:ring-offset-2 focus:outline-hidden ${className}`}
            {...props}
        >
            <Icon icon="battle-net" style="brands" className="h-6" />
            <span>{children}</span>
        </a>
    );
}
