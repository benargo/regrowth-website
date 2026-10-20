import {
    Disclosure,
    DisclosureButton,
    DisclosurePanel,
    Listbox,
    ListboxButton,
    ListboxOption,
    ListboxOptions,
} from "@headlessui/react";
import { usePage } from "@inertiajs/react";
import GameVersionIcon from "@/Components/GameVersions/GameVersionIcon";
import Icon from "@/Components/FontAwesome/Icon";
import Tooltip from "@/Components/Tooltip";
import useGameVersionSwitch from "@/Hooks/useGameVersionSwitch";
import cn from "@/Helpers/cn";
import formatDate from "@/Helpers/FormatDate";
import todayDateString from "@/Helpers/todayDateString";

const isUnreleased = (gameVersion) => Boolean(gameVersion.release_date) && gameVersion.release_date > todayDateString();

/**
 * Check mark for the current game version: a solid check icon inside a
 * non-shrinking span, hidden unless `isVisible`.
 */
function CurrentMark({ isVisible }) {
    return (
        <span className={cn("flex shrink-0", !isVisible && "hidden")}>
            <Icon icon="check" style="solid" className="h-4" />
        </span>
    );
}

/**
 * A game version's title, followed by its release date while unreleased.
 * `className` styles the wrapping column and `titleClassName` the title.
 */
function GameVersionLabel({ gameVersion, className = "", titleClassName = "" }) {
    return (
        <span className={cn("flex flex-1 flex-col", className)}>
            <span className={titleClassName}>{gameVersion.title}</span>
            {isUnreleased(gameVersion) && (
                <span className="text-xs opacity-70">Releases {formatDate(gameVersion.release_date).long}</span>
            )}
        </span>
    );
}

/**
 * Desktop navigation switcher for the current game version. Renders nothing
 * when there is only one game version. `classes` is the nav theme.
 */
export default function GameVersionSwitcher({ classes = {} }) {
    const { gameVersions: { data: gameVersions = [] } = {} } = usePage().props;
    const { current: currentVersion, switchTo, isSwitching } = useGameVersionSwitch();

    if (gameVersions.length <= 1) {
        return null;
    }

    const current = currentVersion ?? gameVersions[0];

    return (
        <Listbox value={current.slug} onChange={switchTo} disabled={isSwitching}>
            <div className="relative">
                <Tooltip body={`Game version: ${current.title}`} position="bottom">
                    <ListboxButton
                        aria-label={`Game version: ${current.title}`}
                        className={cn(
                            "flex min-h-6 items-center gap-2 rounded border px-3 py-2 text-sm transition-colors hover:text-white focus:outline-hidden focus-visible:ring-1 disabled:opacity-60",
                            classes.switcherButton,
                        )}
                    >
                        <GameVersionIcon gameVersion={current} />
                        <span className="flex">
                            <Icon icon="chevron-down" style="regular" className="h-4" />
                        </span>
                    </ListboxButton>
                </Tooltip>
                <ListboxOptions
                    anchor={{ to: "bottom end", gap: 8 }}
                    modal={false}
                    className={cn(
                        "z-30 w-64 rounded-md py-1 shadow-lg focus:outline-hidden",
                        classes.switcherOptions,
                    )}
                >
                    {gameVersions.map((gameVersion) => (
                        <ListboxOption
                            key={gameVersion.slug}
                            value={gameVersion.slug}
                            className={cn(
                                "flex cursor-pointer items-center gap-3 px-3 py-2 text-sm text-white select-none",
                                classes.switcherOption,
                            )}
                        >
                            <GameVersionIcon gameVersion={gameVersion} />
                            <GameVersionLabel gameVersion={gameVersion} className="min-w-0" titleClassName="truncate" />
                            <CurrentMark isVisible={gameVersion.slug === current.slug} />
                        </ListboxOption>
                    ))}
                </ListboxOptions>
            </div>
        </Listbox>
    );
}

/**
 * Mobile row showing the current game version that expands to a list of
 * buttons, one per game version. Renders nothing when there is only one game
 * version.
 */
export function GameVersionSwitchList({ className = "", linkClassName = "" }) {
    const { gameVersions: { data: gameVersions = [] } = {} } = usePage().props;
    const { current: currentVersion, switchTo, isSwitching } = useGameVersionSwitch();

    if (gameVersions.length <= 1) {
        return null;
    }

    const current = currentVersion ?? gameVersions[0];

    return (
        <Disclosure as="div" className={className}>
            <DisclosureButton className={cn(linkClassName, "group gap-3 font-medium")}>
                <GameVersionIcon gameVersion={current} />
                <span className="flex flex-1 flex-col items-start">
                    <span className="text-xs opacity-70">Game version</span>
                    <span>{current.title}</span>
                </span>
                <span className="flex transition-transform group-data-open:rotate-180 motion-reduce:transition-none" aria-hidden="true">
                    <Icon icon="chevron-down" style="regular" className="h-4" />
                </span>
            </DisclosureButton>
            <DisclosurePanel className="space-y-1 py-1 pl-6" role="group" aria-label="Game version">
                {gameVersions.map((gameVersion) => {
                    const isCurrent = gameVersion.slug === current.slug;

                    return (
                        <button
                            key={gameVersion.slug}
                            type="button"
                            disabled={isSwitching}
                            aria-pressed={isCurrent}
                            onClick={() => switchTo(gameVersion.slug)}
                            className={cn(linkClassName, "gap-3 disabled:opacity-60", isCurrent && "font-semibold")}
                        >
                            <GameVersionIcon gameVersion={gameVersion} />
                            <GameVersionLabel gameVersion={gameVersion} className="items-start" />
                            <CurrentMark isVisible={isCurrent} />
                        </button>
                    );
                })}
            </DisclosurePanel>
        </Disclosure>
    );
}
