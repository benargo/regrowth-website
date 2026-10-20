import modelColor from "@/Helpers/ModelColor";

/**
 * The heading of one game version's group on a dataset Index page, ruled in
 * the version's colour, or amber for the group with no game version. An
 * action, such as an "Add" link, sits beside the heading; children render
 * below the rule.
 */
export default function GameVersionGroupHeader({ gameVersion, headingId, action = null, children = null }) {
    const rule = modelColor(gameVersion?.color, ["bg"]);
    const heading = (
        <h2 id={headingId} className="font-serif text-3xl text-white">
            {gameVersion?.title ?? "No game version"}
        </h2>
    );

    return (
        <header className="flex flex-col gap-2">
            {action ? (
                <div className="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-2">
                    {heading}
                    {action}
                </div>
            ) : (
                heading
            )}
            <div
                aria-hidden="true"
                className={`h-0.5 w-full ${rule.className || "bg-amber-600/60"}`}
                style={rule.style}
            />
            {children}
        </header>
    );
}
