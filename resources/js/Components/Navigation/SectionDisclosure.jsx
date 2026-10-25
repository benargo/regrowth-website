import { Disclosure, DisclosureButton, DisclosurePanel } from "@headlessui/react";
import { usePage } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import FlyoutItems from "@/Components/Navigation/FlyoutItems";
import NextRaidCard from "@/Components/Navigation/NextRaidCard";
import { SECTION_HEADER_ICONS } from "@/Components/Navigation/SectionFlyout";
import { SITE_SECTIONS } from "@/Helpers/siteSections";
import cn from "@/Helpers/cn";

/**
 * A mobile menu row for a section that expands to the section's pages, and
 * for Raiding the next raid.
 */
export default function SectionDisclosure({ section, active = false, classes = {} }) {
    const { navigation } = usePage().props;
    const headerIcon = SECTION_HEADER_ICONS[section];
    const isRaiding = section === "raiding";

    return (
        <Disclosure>
            <DisclosureButton
                className={cn(
                    "group flex w-full flex-row items-center rounded-md px-3 py-2 text-left text-sm font-medium text-gray-300 hover:bg-white/5 hover:text-white focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 active:bg-white/10",
                    classes.responsiveNavLink,
                )}
                aria-current={active ? "true" : undefined}
            >
                <Icon icon={headerIcon.icon} style="solid" className="mr-2 h-6" />
                <span className="flex-1">{SITE_SECTIONS[section].label}</span>
                <span
                    className="flex transition-transform group-data-open:rotate-180 motion-reduce:transition-none"
                    aria-hidden="true"
                >
                    <Icon icon="chevron-down" style="regular" className="h-4" />
                </span>
            </DisclosureButton>

            <DisclosurePanel className="flex flex-col gap-2 py-2 pr-1 pl-9">
                {isRaiding && <NextRaidCard navigation={navigation} compact classes={classes} />}
                <FlyoutItems section={section} variant="list" classes={classes} />
            </DisclosurePanel>
        </Disclosure>
    );
}
