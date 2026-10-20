import GameVersionIcon from "@/Components/GameVersions/GameVersionIcon";

export default function GameVersionResultRow({ gameVersion, index, isCurrent, isHighlighted, onMouseEnter, onSelect }) {
    return (
        <button
            type="button"
            id={`search-palette-option-${index}`}
            role="option"
            aria-selected={isHighlighted}
            tabIndex={-1}
            onMouseEnter={onMouseEnter}
            onClick={onSelect}
            className={`flex w-full items-center gap-4 rounded p-2 text-left transition-colors ${
                isHighlighted ? "bg-brown-700" : "bg-brown-800/50 hover:bg-brown-800/70"
            }`}
        >
            <GameVersionIcon gameVersion={gameVersion} className="h-8 w-8" />
            <span className="text-md min-w-0 flex-1 truncate font-bold text-white">{gameVersion.title}</span>
            {isCurrent && <span className="flex-none text-xs text-amber-500">Current</span>}
        </button>
    );
}
