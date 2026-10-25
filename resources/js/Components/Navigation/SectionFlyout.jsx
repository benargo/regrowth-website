import { Popover, PopoverButton, PopoverPanel } from "@headlessui/react";
import { usePage } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import FlyoutItems from "@/Components/Navigation/FlyoutItems";
import LatestDiscussions from "@/Components/Navigation/LatestDiscussions";
import NextRaidCard from "@/Components/Navigation/NextRaidCard";
import { SITE_SECTIONS } from "@/Helpers/siteSections";
import cn from "@/Helpers/cn";

export const SECTION_HEADER_ICONS = {
    raiding: { icon: "dragon", className: "h-9" },
    loot: { icon: "treasure-chest", className: "h-6" },
};

/**
 * A header entry for a section that opens a panel, hung from the main
 * navigation bar, with the section's highlights and pages.
 */
export default function SectionFlyout({ section, active = false, classes = {} }) {
    const { navigation } = usePage().props;
    const headerIcon = SECTION_HEADER_ICONS[section];

    return (
        <Popover>
            <PopoverButton
                className={cn(
                    "group hover:bg-brown-700/80 flex min-h-9 flex-row items-center rounded-sm border-b border-transparent px-4 text-sm font-bold transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400",
                    classes.navLink,
                )}
                aria-current={active ? "true" : undefined}
            >
                <Icon icon={headerIcon.icon} style="solid" className={cn("mr-2", headerIcon.className)} />
                {SITE_SECTIONS[section].label}
                <span
                    className="ml-2 flex transition-transform group-data-open:rotate-180 motion-reduce:transition-none"
                    aria-hidden="true"
                >
                    <Icon icon="chevron-down" style="regular" className="h-4" />
                </span>
            </PopoverButton>

            <PopoverPanel
                transition
                className={cn(
                    "absolute inset-x-0 top-full z-30 mt-2 rounded-lg border p-6 shadow-lg backdrop-blur-xl transition duration-200 ease-out data-closed:-translate-y-1 data-closed:opacity-0 data-leave:duration-150 data-leave:ease-in motion-reduce:transition-none",
                    classes.flyoutPanel,
                )}
            >
                {section === "raiding" ? (
                    <div className="grid grid-cols-[1fr_2fr] gap-8">
                        <NextRaidCard navigation={navigation} classes={classes} />
                        <FlyoutItems section="raiding" variant="tiles" classes={classes} />
                    </div>
                ) : (
                    <div className="grid grid-cols-[2fr_1fr] gap-10">
                        <LatestDiscussions navigation={navigation} classes={classes} />
                        <div>
                            <h2 className={cn("mb-2 font-serif text-xl text-amber-300", classes.flyoutHeading)}>
                                Browse
                            </h2>
                            <FlyoutItems section="loot" variant="browse" classes={classes} />
                        </div>
                    </div>
                )}
            </PopoverPanel>
        </Popover>
    );
}
