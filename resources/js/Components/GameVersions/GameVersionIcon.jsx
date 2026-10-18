import cn from "@/Helpers/cn";
import modelColor from "@/Helpers/ModelColor";

/**
 * A game version's icon image, or a coloured mark when it has no icon.
 */
export default function GameVersionIcon({ gameVersion, className = "" }) {
    if (gameVersion.icon_url) {
        return <img src={gameVersion.icon_url} alt="" className={cn("h-5 w-5 shrink-0 object-contain", className)} />;
    }

    const { className: colorClass, style } = modelColor(gameVersion.color, ["bg"]);

    return (
        <span
            aria-hidden="true"
            className={cn("inline-block h-5 w-5 shrink-0 rounded-full bg-gray-500", colorClass, className)}
            style={style}
        />
    );
}
