import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import WarcraftLogsLogo from "@/Components/WarcraftLogs/Logo";
import GameVersionIcon from "@/Components/GameVersions/GameVersionIcon";
import useGameVersionSwitch from "@/Hooks/useGameVersionSwitch";
import { SITE_SECTIONS, visibleSectionItems } from "@/Helpers/siteSections";
import cn from "@/Helpers/cn";

const FOOTER_COLUMN_LINK = "flex min-h-8 flex-row items-center gap-2 py-1 transition-colors";

/**
 * A fixed-width slot that centres a footer link's icon. Renders an empty slot
 * of the same width when given no children.
 */
function FooterLinkIcon({ children }) {
    return (
        <span aria-hidden="true" className="flex w-5 shrink-0 items-center justify-center">
            {children}
        </span>
    );
}

/**
 * A footer column heading, rendered as a link with hover states when `href`
 * is given.
 */
function FooterHeading({ classes, href, children }) {
    return (
        <h2 className="mb-2 font-serif text-base">
            {href ? (
                <Link
                    href={href}
                    className={cn("inline-flex min-h-6 items-center transition-colors", classes.footerHeadingLink)}
                >
                    {children}
                </Link>
            ) : (
                <span className={cn("inline-flex min-h-6 items-center", classes.footerHeading)}>{children}</span>
            )}
        </h2>
    );
}

/**
 * The Raiding or Loot column shown from `lg` upwards: a heading linking to the
 * section hub above the section's pages the viewer may see.
 */
function SectionColumn({ section, classes, permissions, linkClass }) {
    const { label, hubRouteName } = SITE_SECTIONS[section];

    return (
        <div className="hidden lg:block">
            <FooterHeading classes={classes} href={route(hubRouteName)}>
                {label}
            </FooterHeading>
            <ul className="flex flex-col">
                {visibleSectionItems(section, permissions).map((item) => (
                    <li key={item.key}>
                        <Link href={route(item.routeName)} className={linkClass}>
                            <FooterLinkIcon>
                                <Icon icon={item.icon} style="solid" className="h-5 w-5" />
                            </FooterLinkIcon>
                            <span className="text-nowrap">{item.label}</span>
                        </Link>
                    </li>
                ))}
            </ul>
        </div>
    );
}

function RosterLink({ linkClass }) {
    return (
        <li>
            <Link href={route("roster.index")} className={linkClass}>
                <FooterLinkIcon>
                    <Icon icon="users" style="solid" className="h-5 w-5" />
                </FooterLinkIcon>
                <span className="text-nowrap">Roster</span>
            </Link>
        </li>
    );
}

function WarcraftLogsLink({ linkClass, url }) {
    if (!url) {
        return null;
    }

    return (
        <li>
            <a href={url} className={linkClass} rel="noopener noreferrer">
                <FooterLinkIcon>
                    <WarcraftLogsLogo className="h-5 w-5" />
                </FooterLinkIcon>
                <span className="text-nowrap">Warcraft Logs</span>
            </a>
        </li>
    );
}

function DiscordLink({ linkClass, discordInviteUrl }) {
    return (
        <li>
            <a href={discordInviteUrl} target="_blank" rel="noopener noreferrer" className={linkClass}>
                <FooterLinkIcon>
                    <Icon icon="discord" style="brands" className="h-5 w-5" />
                </FooterLinkIcon>
                <span className="text-nowrap">Discord</span>
            </a>
        </li>
    );
}

/**
 * The site footer: brand, section columns (lg and up), guild links, game
 * version switcher, about links and the disclaimer.
 */
export default function Footer({ classes, discordInviteUrl, warcraftLogsUrl, gameVersions = [], permissions = [] }) {
    const { current: currentGameVersion, switchTo, isSwitching } = useGameVersionSwitch();
    const hasGameVersionColumn = gameVersions.length > 1;
    const footerLinkClass = cn(FOOTER_COLUMN_LINK, classes.footerLink);

    return (
        <footer className={cn("border-t-8 py-5", classes.footer)} id="footer">
            <div className="container mx-auto px-4">
                <div
                    className={cn(
                        "my-4 grid grid-cols-1 gap-8 md:my-6 md:grid-cols-2 md:gap-7",
                        hasGameVersionColumn
                            ? "lg:grid-cols-[minmax(min-content,1fr)_repeat(5,auto)]"
                            : "lg:grid-cols-[minmax(min-content,1fr)_repeat(4,auto)]",
                    )}
                >
                    {/* Brand */}
                    <div className="flex flex-col gap-3 md:col-span-2 md:flex-row md:items-center md:justify-between lg:col-span-1 lg:flex-col lg:items-start lg:justify-start">
                        <div className="flex items-center gap-4">
                            <Link href="/" title="Regrowth" className="flex-none">
                                <img src="/images/guild_emblem.webp" alt="" className="h-20 object-contain" />
                                <span className="sr-only">Regrowth</span>
                            </Link>
                            <img
                                src="/images/logo_forever.webp"
                                alt="World of Warcraft: Forever logo"
                                className="h-20 min-w-0 object-contain"
                            />
                        </div>
                        <div className={cn("flex items-center", classes.footerText)}>
                            <Icon icon="copyright" style="regular" className="mr-2 h-5 w-5" />
                            <span className="text-nowrap">{new Date().getFullYear()} Regrowth</span>
                        </div>
                    </div>

                    <SectionColumn
                        section="raiding"
                        classes={classes}
                        permissions={permissions}
                        linkClass={footerLinkClass}
                    />
                    <SectionColumn
                        section="loot"
                        classes={classes}
                        permissions={permissions}
                        linkClass={footerLinkClass}
                    />

                    {/* Guild (below lg) */}
                    <nav aria-label="Guild" className="lg:hidden">
                        <FooterHeading classes={classes}>Guild</FooterHeading>
                        <ul className="flex flex-col">
                            <RosterLink linkClass={footerLinkClass} />
                            <li>
                                <Link href={route("raiding.index")} className={footerLinkClass}>
                                    <FooterLinkIcon />
                                    <span className="text-nowrap">Raiding</span>
                                </Link>
                            </li>
                            <li>
                                <Link href={route("loot.index")} className={footerLinkClass}>
                                    <FooterLinkIcon />
                                    <span className="text-nowrap">Loot</span>
                                </Link>
                            </li>
                        </ul>
                    </nav>

                    {/* Connect (below lg) */}
                    <nav aria-label="Connect" className="lg:hidden">
                        <FooterHeading classes={classes}>Connect</FooterHeading>
                        <ul className="flex flex-col">
                            <WarcraftLogsLink linkClass={footerLinkClass} url={warcraftLogsUrl} />
                            <DiscordLink linkClass={footerLinkClass} discordInviteUrl={discordInviteUrl} />
                        </ul>
                    </nav>

                    {/* Guild (lg and up) */}
                    <nav aria-label="Guild" className="hidden lg:block">
                        <FooterHeading classes={classes}>Guild</FooterHeading>
                        <ul className="flex flex-col">
                            <RosterLink linkClass={footerLinkClass} />
                            <WarcraftLogsLink linkClass={footerLinkClass} url={warcraftLogsUrl} />
                            <DiscordLink linkClass={footerLinkClass} discordInviteUrl={discordInviteUrl} />
                        </ul>
                    </nav>

                    {/* Game versions */}
                    {hasGameVersionColumn && (
                        <nav aria-label="Where we play">
                            <FooterHeading classes={classes}>Where we play</FooterHeading>
                            <ul className="flex flex-col">
                                {gameVersions.map((gameVersion) => {
                                    const isCurrent = gameVersion.slug === currentGameVersion?.slug;

                                    return (
                                        <li key={gameVersion.slug}>
                                            <button
                                                type="button"
                                                disabled={isSwitching}
                                                onClick={() => switchTo(gameVersion.slug)}
                                                aria-current={isCurrent ? "true" : undefined}
                                                className={cn(
                                                    FOOTER_COLUMN_LINK,
                                                    "w-full text-left disabled:opacity-60",
                                                    classes.footerLink,
                                                    isCurrent && "font-bold",
                                                )}
                                            >
                                                <FooterLinkIcon>
                                                    <GameVersionIcon gameVersion={gameVersion} />
                                                </FooterLinkIcon>
                                                <span className="min-w-0">
                                                    {gameVersion.title}
                                                    {isCurrent && (
                                                        <span className="ml-2 inline-block rounded-full border border-current px-2 align-middle text-xs font-normal whitespace-nowrap opacity-70">
                                                            Current
                                                        </span>
                                                    )}
                                                </span>
                                            </button>
                                        </li>
                                    );
                                })}
                            </ul>
                        </nav>
                    )}

                    {/* About */}
                    <nav aria-label="About">
                        <FooterHeading classes={classes}>About</FooterHeading>
                        <ul className="flex flex-col">
                            <li>
                                <Link href={route("privacypolicy")} className={footerLinkClass}>
                                    <FooterLinkIcon>
                                        <Icon icon="user-secret" style="solid" className="h-5 w-5" />
                                    </FooterLinkIcon>
                                    <span className="text-nowrap">Privacy policy</span>
                                </Link>
                            </li>
                            <li>
                                <Link href={route("battlenet-usage")} className={footerLinkClass}>
                                    <FooterLinkIcon>
                                        <Icon icon="battle-net" style="brands" className="h-5 w-5" />
                                    </FooterLinkIcon>
                                    <span className="text-nowrap">Battle.net API Usage</span>
                                </Link>
                            </li>
                            <li>
                                <a
                                    href="https://benargo.com"
                                    target="_blank"
                                    rel="noopener noreferrer"
                                    title="Ben Argo"
                                    className={footerLinkClass}
                                >
                                    <FooterLinkIcon>
                                        <Icon icon="safari" style="brands" className="h-5 w-5" />
                                    </FooterLinkIcon>
                                    <span className="text-nowrap">A Fizzywigs Production</span>
                                </a>
                            </li>
                        </ul>
                    </nav>
                </div>
                <div className="mt-4 md:mt-2">
                    <p className="text-center text-xs text-gray-400 md:text-left">
                        Disclaimer: Classic is a trademark, and World of Warcraft and Warcraft are trademarks or
                        registered trademarks of Blizzard Entertainment, Inc., in the U.S. and/or other countries. All
                        related materials, logos, and images are copyright &copy; Blizzard Entertainment, Inc. Regrowth
                        is in no way associated with or endorsed by Blizzard Entertainment.
                    </p>
                </div>
            </div>
        </footer>
    );
}
