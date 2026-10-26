import { Link } from "@inertiajs/react";
import { Can } from "@/Components/Authorizable";
import GameOptionsPanel, { GameOptionsCheckbox } from "@/Components/LootTool/GameOptionsPanel";
import GameTooltip, { GameTooltipHeading, GameTooltipStat } from "@/Components/LootTool/GameTooltip";
import LootToolNav from "@/Components/LootTool/LootToolNav";
import Card from "@/Components/Card";
import CopyableCode from "@/Components/CopyableCode";
import FormPageLayout, { FormPageSection } from "@/Components/FormPage";
import Icon from "@/Components/FontAwesome/Icon";
import { linkButtonClassName } from "@/Components/FormControls";
import SectionNav from "@/Components/Navigation/SectionNav";
import PageContainer from "@/Components/PageContainer";
import SharedHeader from "@/Components/SharedHeader";
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from "@/Components/Table";
import { SITE_SECTIONS } from "@/Helpers/siteSections";
import Master from "@/Layouts/Master";

const SECTIONS = [
    { value: "about", label: "About" },
    { value: "item-tooltips", label: "Item tooltips" },
    { value: "player-tooltips", label: "Player tooltips" },
    { value: "settings", label: "Settings" },
    { value: "commands", label: "Commands" },
    { value: "optional-modules", label: "Optional modules" },
    { value: "loot-council", label: "For the loot council" },
    { value: "credits", label: "Credits" },
];

const COMMANDS = [
    { command: "/rg", description: "Opens or closes the main window. /regrowth does the same." },
    { command: "/rg toggle bias", description: "Shows or hides loot bias tooltips outside of raids." },
    { command: "/rg toggle players", description: "Shows or hides player tooltips outside of raids." },
    { command: "/rg toggle wishlist", description: "Shows or hides wishlist tooltips outside of raids." },
    { command: "/rg history", description: "Opens RG History, if it’s installed. /rg h does the same." },
    { command: "/rg bias", description: "Opens RG Bias, if it’s installed. /rg b does the same." },
];

const secondaryLinkButtonClassName =
    "bg-brown-800 text-gray-200 inset-ring inset-ring-amber-600 hover:bg-brown-700 hover:text-white " +
    "focus-visible:outline-amber-400 inline-flex items-center gap-2 rounded-md px-4 py-2 text-sm font-semibold " +
    "focus-visible:outline-2 focus-visible:outline-offset-2";

function ExternalLink({ href, className, children }) {
    return (
        <a href={href} target="_blank" rel="noopener noreferrer" className={className}>
            {children}
        </a>
    );
}

export default function Overview({ curseforgeUrl, githubUrl, itemIconUrl }) {
    return (
        <Master title="Regrowth Loot Tool">
            <SharedHeader
                title="Regrowth Loot Tool"
                subtitle="Loot council info in your in-game tooltips"
                backgroundClass={SITE_SECTIONS.addons.headerBackgroundClass}
            />
            <LootToolNav />
            <PageContainer>
                <FormPageLayout sections={SECTIONS} sidebarClassName="hidden lg:flex">
                    <FormPageSection id="about" title="About the addon">
                        <div className="flex max-w-prose flex-col gap-4 text-lg text-gray-200">
                            <p>
                                Regrowth Loot Tool is a World of Warcraft addon for the Regrowth guild on Thunderstrike
                                EU. It puts the loot council’s information (loot biases, wishlists, raid attendance and
                                loot history) straight into your tooltips, so it’s there when a decision is being made.
                            </p>
                            <p className="text-base text-gray-400">
                                The addon is still in beta, so some features may not always work as expected.
                            </p>
                        </div>
                        <div className="flex flex-row flex-wrap gap-3">
                            <ExternalLink href={curseforgeUrl} className={linkButtonClassName}>
                                <Icon icon="download" style="solid" className="h-4" aria-hidden="true" />
                                Download on CurseForge
                            </ExternalLink>
                            <ExternalLink href={githubUrl} className={secondaryLinkButtonClassName}>
                                <Icon icon="github" style="brands" className="h-4" aria-hidden="true" />
                                View on GitHub
                            </ExternalLink>
                        </div>
                    </FormPageSection>

                    <FormPageSection id="item-tooltips" title="Item tooltips">
                        <div className="flex max-w-prose flex-col gap-4 text-gray-200">
                            <p>
                                Hover over an item, or click its link in chat, to see the guild’s loot bias for it. Each
                                spec is shown with its icon and class colour. <strong>&gt;</strong> means “higher
                                priority than” and <strong>=</strong> means equal priority. Items without any priorities
                                show <strong>MS &gt; OS</strong>.
                            </p>
                            <p>
                                Below the bias, the tooltip lists the players who have the item on their wishlist. The
                                list is left out when nobody wants it.
                            </p>
                        </div>
                        <GameTooltip
                            label="Example of an item tooltip with the Regrowth Bias and Wanted by lines"
                            iconUrl={itemIconUrl}
                            className="max-w-lg"
                        >
                            <p className="text-quality-epic text-base">Adornment of Stolen Souls</p>
                            <p>Binds when picked up</p>
                            <p>Neck</p>
                            <p>
                                +18 Stamina <span className="text-[#ffd100]">(+20.7)</span>
                            </p>
                            <p>
                                +20 Intellect <span className="text-[#ffd100]">(+21, +0.26% Spell Crit)</span>
                            </p>
                            <p>Requires Level 70</p>
                            <p className="text-[#1eff00]">
                                Equip: Improves spell critical strike rating by 23{" "}
                                <span className="text-[#ffd100]">(+1.04%)</span>.
                            </p>
                            <p className="text-[#1eff00]">
                                Equip: Increases damage and healing done by magical spells and effects by up to 28{" "}
                                <span className="text-[#ffd100]">(+28 Spell Dmg, Heal)</span>.
                            </p>
                            <p className="mt-3 flex items-center gap-2 text-[#3fc7eb]">
                                <Icon icon="sack" style="solid" className="text-xs text-amber-600" aria-hidden="true" />
                                Karazhan - Prince Malchezaar
                            </p>
                            <GameTooltipHeading className="mt-3">Regrowth Bias:</GameTooltipHeading>
                            <p>
                                <span className="text-playable-class-shaman">Elemental Shaman</span> &gt;{" "}
                                <span className="text-playable-class-druid">Balance Druid</span> &gt;{" "}
                                <span className="text-playable-class-warlock">Destruction Warlock</span> ={" "}
                                <span className="text-playable-class-mage">Mage</span>
                            </p>
                            <GameTooltipHeading>Wanted by:</GameTooltipHeading>
                            <p>Nex</p>
                            <p>Juice</p>
                        </GameTooltip>
                    </FormPageSection>

                    <FormPageSection id="player-tooltips" title="Player tooltips">
                        <div className="flex flex-col gap-6 md:flex-row md:items-start">
                            <ul className="flex max-w-prose list-disc flex-col gap-2 pl-5 text-gray-200">
                                <li>
                                    <strong>Attendance</strong>: the player’s raid attendance as a percentage.
                                </li>
                                <li>
                                    <strong>Total Loot</strong>: items won in the current phase (green) and the previous
                                    phase (yellow). Older phases, disenchants and off-spec wins are left out.
                                </li>
                                <li>
                                    <strong>Last Win</strong>: the date of the player’s most recent loot.
                                </li>
                            </ul>
                            <GameTooltip
                                label="Example of the guild raid stats on a player tooltip"
                                className="md:w-80"
                            >
                                <p className="text-base text-[#40ff40]">Nex</p>
                                <GameTooltipHeading>Guild Raid Stats:</GameTooltipHeading>
                                <GameTooltipStat label="Attendance:">87%</GameTooltipStat>
                                <GameTooltipStat label="Total Loot:">
                                    6 <span className="text-[#1eff00]">(P2: 4)</span>{" "}
                                    <span className="text-[#ffd100]">(P1: 2)</span>
                                </GameTooltipStat>
                                <GameTooltipStat label="Last Win:">14/10/2026</GameTooltipStat>
                            </GameTooltip>
                        </div>
                    </FormPageSection>

                    <FormPageSection id="settings" title="Settings">
                        <p className="max-w-prose text-gray-200">
                            The tooltips always show while you’re in a raid. To see them outside of raids too, open the
                            main window with <CopyableCode>/rg</CopyableCode> and tick the tooltip types you want under
                            Config.
                        </p>
                        <GameOptionsPanel title="Config" label="The Config options in the addon’s main window">
                            <GameOptionsCheckbox>Display Loot bias tooltips outside of raid.</GameOptionsCheckbox>
                            <GameOptionsCheckbox>Display Player tooltips outside of raid.</GameOptionsCheckbox>
                            <GameOptionsCheckbox>Display Wishlist tooltips outside of raid.</GameOptionsCheckbox>
                        </GameOptionsPanel>
                    </FormPageSection>

                    <FormPageSection id="commands" title="Commands">
                        <Table>
                            <TableHead>
                                <tr>
                                    <TableHeader className="text-left">Command</TableHeader>
                                    <TableHeader className="text-left">What it does</TableHeader>
                                </tr>
                            </TableHead>
                            <TableBody>
                                {COMMANDS.map(({ command, description }) => (
                                    <TableRow key={command}>
                                        <TableCell className="whitespace-nowrap">
                                            <CopyableCode>{command}</CopyableCode>
                                        </TableCell>
                                        <TableCell className="text-gray-200">{description}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </FormPageSection>

                    <FormPageSection id="optional-modules" title="Optional modules">
                        <p className="max-w-prose text-gray-200">
                            Two companion addons add extra windows. Both need Regrowth Loot Tool and won’t work on their
                            own.
                        </p>
                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <Card title="RG History">
                                <div className="flex flex-col gap-3 text-gray-200">
                                    <p>A window for everything the addon has tracked:</p>
                                    <ul className="flex list-disc flex-col gap-1 pl-5">
                                        <li>
                                            <strong>Overview</strong>: every player’s attendance, total loot and
                                            per-phase loot, in a sortable table.
                                        </li>
                                        <li>
                                            <strong>Player Loot</strong>: everything a player has won, with dates and
                                            phases.
                                        </li>
                                        <li>
                                            <strong>Loot Data</strong>: who has won an item, and when, in one phase or
                                            all of them.
                                        </li>
                                    </ul>
                                    <p>
                                        Open it with <CopyableCode>/rg history</CopyableCode>
                                    </p>
                                </div>
                            </Card>
                            <Card title="RG Bias">
                                <div className="flex flex-col gap-3 text-gray-200">
                                    <p>
                                        A searchable list of the loot council’s item priorities, by raid and boss in
                                        progression order and colour-coded by phase. Search for an item by name instead
                                        of scrolling a raid’s whole loot table.
                                    </p>
                                    <p>
                                        Open it with <CopyableCode>/rg bias</CopyableCode>
                                    </p>
                                </div>
                            </Card>
                        </div>
                    </FormPageSection>

                    <FormPageSection id="loot-council" title="For the loot council">
                        <div className="flex max-w-prose flex-col gap-4 text-gray-200">
                            <p>
                                Officers import the guild’s data into the addon, and the addon shares it with every loot
                                council member who is online. When more than one officer is online, the one with the
                                newest data sends it, so nobody has to send it by hand.
                            </p>
                            <p>
                                The addon only accepts shared data from guild officers it can verify, and everyone on
                                the loot council needs to run the same version for sharing to work.
                            </p>
                            <Can permission="view-officer-dashboard">
                                <p>
                                    Get the guild’s data from the{" "}
                                    <Link href={route("addons.loot-tool.export")} className="text-amber-400 underline">
                                        addon export page
                                    </Link>
                                    .
                                </p>
                            </Can>
                        </div>
                    </FormPageSection>

                    <FormPageSection id="credits" title="Credits">
                        <ul className="flex max-w-prose flex-col gap-2 text-gray-200">
                            <li>
                                Prototype, addon behaviour and user design by <strong>Jucie</strong> (SoulJuice).
                            </li>
                            <li>
                                Implementation by <strong>Amy</strong> (Kyukon / Khamira).
                            </li>
                            <li>
                                <ExternalLink href={githubUrl} className="text-amber-400 underline">
                                    Source code on GitHub
                                </ExternalLink>
                            </li>
                        </ul>
                    </FormPageSection>
                </FormPageLayout>
            </PageContainer>
        </Master>
    );
}
