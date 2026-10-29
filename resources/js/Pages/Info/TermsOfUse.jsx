import { Link } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import OnThisPage from "@/Components/OnThisPage";
import ExternalLink from "@/Components/ExternalLink";
import Band from "@/Components/Band/Band";
import BandHeading from "@/Components/Band/BandHeading";
import JoinDiscordButton from "@/Components/Discord/JoinDiscordButton";

const SECTIONS = [
    { value: "about", label: "About these terms" },
    { value: "who-we-are", label: "Who runs this website" },
    { value: "your-account", label: "Your account" },
    { value: "access", label: "Roles and permissions" },
    { value: "conduct", label: "How we expect you to behave" },
    { value: "comments", label: "Comments and reactions" },
    { value: "guild-information", label: "Setting guild information" },
    { value: "your-content", label: "Content you contribute" },
    { value: "misuse", label: "Technical misuse" },
    { value: "moderation", label: "Moderation" },
    { value: "accuracy", label: "Accuracy of information" },
    { value: "third-parties", label: "Blizzard and other services" },
    { value: "availability", label: "Availability" },
    { value: "liability", label: "Our liability" },
    { value: "changes", label: "Changes to these terms" },
    { value: "law", label: "Governing law" },
    { value: "contact", label: "How to contact us" },
];

const LAST_UPDATED = "29 October 2026";

const INLINE_LINK_CLASS =
    "text-amber-400 underline decoration-amber-700 underline-offset-4 transition-colors hover:text-amber-300 hover:decoration-amber-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400";

/**
 * A titled terms section, labelled by its heading.
 */
function Section({ id, title, children }) {
    return (
        <section id={id} aria-labelledby={`${id}-heading`} className="flex flex-col gap-4">
            <BandHeading level={3} id={`${id}-heading`}>
                {title}
            </BandHeading>
            {children}
        </section>
    );
}

function List({ children }) {
    return <ul className="flex list-outside list-disc flex-col gap-2 pl-5 marker:text-amber-600">{children}</ul>;
}

/**
 * A list of named terms, each with its description beneath.
 */
function TermList({ terms }) {
    return (
        <dl className="flex flex-col gap-4 border-l border-amber-700/60 pl-5">
            {terms.map(({ term, description }) => (
                <div key={term}>
                    <dt className="font-serif text-lg text-amber-200">{term}</dt>
                    <dd>{description}</dd>
                </div>
            ))}
        </dl>
    );
}

const GUILD_INFORMATION = [
    {
        term: "Planned absences",
        description:
            "Record only your own absences, unless you're an officer recording one on a member's behalf. Keep reasons and notes brief, and don't include anyone else's private details.",
    },
    {
        term: "Loot priorities and item notes",
        description:
            "Set priorities and notes in line with the guild's agreed loot rules. Don't change them to favour yourself or your friends, or to settle a disagreement.",
    },
    {
        term: "Raid plans, groups and assignments",
        description:
            "Make changes in good faith to help the raid run. Other members rely on these plans, so don't rearrange them as a joke or without the raid leader's agreement.",
    },
    {
        term: "Characters and reports",
        description:
            "Link only characters that belong to you, and add only Warcraft Logs reports from the guild's own raids.",
    },
    {
        term: "Raids, bosses, strategies and other guild records",
        description:
            "Keep these accurate and on topic. Uploaded images must be suitable for everyone in the guild to see, and you must have the right to use them.",
    },
];

export default function TermsOfUse() {
    return (
        <Master title="Terms of Use">
            <SharedHeader
                title="Terms of Use"
                subtitle={`Last updated ${LAST_UPDATED}`}
                backgroundClass="bg-undercity-bank"
            />

            <Band tone="light">
                <div className="flex flex-col gap-10 lg:flex-row lg:items-start lg:gap-16">
                    <OnThisPage sections={SECTIONS} className="hidden lg:flex lg:w-1/4 lg:shrink-0" />

                    <div className="flex max-w-prose min-w-0 flex-1 flex-col gap-14 leading-relaxed text-gray-300">
                        <Section id="about" title="About these terms">
                            <p className="text-lg text-amber-100">
                                These terms set out how you may use the Regrowth website and how we expect you to behave
                                while you're here. By using the website, you agree to them. If you don't agree, please
                                don't use it.
                            </p>
                            <p>
                                In short: be kind, be honest, and use the website to help the guild. These terms are not
                                meant to catch anyone out. They exist so that everyone knows where they stand, and so
                                that the people who look after the website on the guild's behalf are not held
                                responsible for what others do with it.
                            </p>
                            <p>
                                Our{" "}
                                <Link href={route("privacypolicy")} className={INLINE_LINK_CLASS}>
                                    privacy policy
                                </Link>{" "}
                                explains what personal data we collect and how we use it.
                            </p>
                        </Section>

                        <Section id="who-we-are" title="Who runs this website">
                            <p>
                                Regrowth is a World of Warcraft guild. It is an informal group of players, not a
                                company, charity or other legal entity. This website is built, hosted and paid for by an
                                individual guild member, as a volunteer, for the guild's benefit. The guild's officers
                                manage what appears on it.
                            </p>
                            <p>
                                In these terms, "we" and "us" means the guild's officers and the member who hosts the
                                website, each acting in a personal, voluntary capacity. "You" means anyone who visits or
                                uses the website.
                            </p>
                        </Section>

                        <Section id="your-account" title="Your account">
                            <p>
                                You sign in to the website with your Discord account. You are responsible for everything
                                done while you're signed in, so:
                            </p>
                            <List>
                                <li>Keep your Discord account secure, and don't share it with anyone else</li>
                                <li>Sign out on shared or public devices</li>
                                <li>Don't sign in as, or pretend to be, someone else</li>
                                <li>Tell an officer straight away if you think someone else has used your account</li>
                            </List>
                            <p>
                                Your use of Discord and Battle.net is also governed by their own terms of service, which
                                you've agreed to separately.
                            </p>
                        </Section>

                        <Section id="access" title="Roles and permissions">
                            <p>
                                What you can see and change on the website depends on the roles you hold in our Discord
                                server. Officers may grant, change or remove roles and permissions at any time, for any
                                reason.
                            </p>
                            <p>
                                Being able to change something doesn't always mean you should. Use the tools you have
                                access to only for the purpose they were given to you, and only in the way the guild's
                                officers expect. If you find you can see or change something you don't think you
                                should, please don't use it. Tell an officer instead.
                            </p>
                        </Section>

                        <Section id="conduct" title="How we expect you to behave">
                            <p>
                                The same standards apply on this website as in the guild, in game and in our Discord
                                server. When you use the website, you must not:
                            </p>
                            <List>
                                <li>Harass, bully, threaten or intimidate anyone</li>
                                <li>
                                    Post anything hateful or discriminatory, including on the basis of race, ethnicity,
                                    nationality, religion, gender, gender identity, sexual orientation, disability or
                                    age
                                </li>
                                <li>Post sexually explicit, violent or otherwise offensive material</li>
                                <li>
                                    Share anyone's personal information, such as their real name, address, photos or
                                    contact details, without their permission
                                </li>
                                <li>Impersonate another member, an officer, or anyone else</li>
                                <li>Knowingly post false or misleading information</li>
                                <li>Spam, advertise, or post links to scams, cheats or malicious websites</li>
                                <li>Post anything that infringes someone else's copyright or other rights</li>
                                <li>Do anything that is illegal, or that encourages others to break the law</li>
                                <li>
                                    Promote anything that breaks Blizzard's terms of service, such as gold buying,
                                    account sharing or botting
                                </li>
                            </List>
                            <p>
                                Disagreements happen. Raise them respectfully, and take anything heated to an officer
                                rather than playing it out on the website.
                            </p>
                        </Section>

                        <Section id="comments" title="Comments and reactions">
                            <p>
                                Comments and reactions are there to share useful information and discuss guild matters,
                                such as loot, bosses and raids. When you comment:
                            </p>
                            <List>
                                <li>Stay on topic, and keep it constructive</li>
                                <li>Criticise ideas and decisions, not people</li>
                                <li>Don't use reactions to mock or pile on to someone</li>
                                <li>Remember that other guild members, including officers, can read what you post</li>
                            </List>
                            <p>
                                We keep a history of edits to every comment, and deleted comments are hidden rather
                                than erased straight away. Officers can see this history, so editing or deleting a
                                comment doesn't undo having posted it.
                            </p>
                        </Section>

                        <Section id="guild-information" title="Setting guild information">
                            <p>
                                Depending on your permissions, you may be able to set information that other members
                                rely on. This information updates live, and may be posted to our Discord server. Please
                                take care with it:
                            </p>
                            <TermList terms={GUILD_INFORMATION} />
                            <p>
                                If you make a mistake, put it right or let an officer know. If you see something you
                                think is wrong, raise it with an officer rather than changing it yourself, unless
                                correcting it is part of your role.
                            </p>
                        </Section>

                        <Section id="your-content" title="Content you contribute">
                            <p>
                                You keep ownership of anything you post or upload to the website, and you are
                                responsible for it. By posting it, you confirm that you have the right to do so, and you
                                give us permission to store, display, edit, share with the guild and post to our
                                Discord server, for as long as the website needs it.
                            </p>
                            <p>
                                The views expressed in content contributed by members are their own. They are not the
                                views of the guild, its officers or the website's host.
                            </p>
                        </Section>

                        <Section id="misuse" title="Technical misuse">
                            <p>You must not:</p>
                            <List>
                                <li>
                                    Try to access parts of the website, accounts or data that you haven't been given
                                    access to
                                </li>
                                <li>Probe, scan or test the website for security weaknesses</li>
                                <li>Interfere with, overload or disrupt the website or the servers it runs on</li>
                                <li>Upload viruses or any other harmful code</li>
                                <li>
                                    Use bots, scrapers or other automated tools to access the website or collect data
                                    from it, without an officer's permission
                                </li>
                            </List>
                            <p>
                                If you come across a security problem by accident, please don't explore it further.
                                Report it to an officer privately, and we'll thank you for it.
                            </p>
                        </Section>

                        <Section id="moderation" title="Moderation">
                            <p>
                                We don't monitor everything posted on the website, and we're not obliged to. But we may,
                                at our discretion and without notice:
                            </p>
                            <List>
                                <li>Edit, hide or remove any comment, note, upload or other content</li>
                                <li>Undo changes made to guild information</li>
                                <li>Remove your permissions, or your access to the website altogether</li>
                            </List>
                            <p>
                                Breaking these terms may also be dealt with under the guild's own rules, including in
                                game and on Discord. Where something may be illegal, we may report it to the relevant
                                platform or authority.
                            </p>
                        </Section>

                        <Section id="accuracy" title="Accuracy of information">
                            <p>
                                Much of the information on the website, such as the roster, attendance, raid
                                performance and loot priorities, is drawn from other services or entered by members. We
                                try to keep it accurate, but we can't guarantee that it is complete, correct or up to
                                date.
                            </p>
                            <p>
                                Information on the website is a guide only. It does not create any promise or
                                entitlement, for example to a raid spot or an item. Decisions about the guild, its raids
                                and its loot are made by the officers, and the website only records them.
                            </p>
                        </Section>

                        <Section id="third-parties" title="Blizzard and other services">
                            <p>
                                Regrowth is not affiliated with or endorsed by Blizzard Entertainment, Discord,
                                Warcraft Logs, Raid-Helper or Wowhead. Your use of World of Warcraft remains subject to
                                the{" "}
                                <ExternalLink href="https://www.blizzard.com/en-us/legal/fba4d00f-c7e4-4883-b8b9-1b4500a402ea/blizzard-end-user-license-agreement">
                                    Blizzard End User License Agreement
                                </ExternalLink>
                                , and nothing on this website changes that.
                            </p>
                            <p>
                                The website links to, and displays content from, other websites. We aren't responsible
                                for their content, availability or how they handle your data.
                            </p>
                        </Section>

                        <Section id="availability" title="Availability">
                            <p>
                                The website is provided free of charge, by volunteers, in their spare time. It is
                                provided "as is" and "as available". We may change, suspend or close any part of it, or
                                the whole website, at any time and without notice.
                            </p>
                            <p>
                                We don't guarantee that the website will always be available, free of errors, or that
                                anything stored on it will be kept. If something matters to you, keep your own copy.
                            </p>
                        </Section>

                        <Section id="liability" title="Our liability">
                            <p>
                                As far as the law allows, neither the guild's officers nor the website's host are liable
                                for any loss or damage arising from your use of the website, from content posted by
                                other members, or from the website being unavailable. This includes, for example, any
                                in-game loss, missed raid or loot decision.
                            </p>
                            <p>
                                You are responsible for what you post and do on the website. If your breach of these
                                terms leads to a claim against an officer or the website's host, you agree to take
                                responsibility for it.
                            </p>
                            <p>
                                Nothing in these terms limits any liability that cannot be limited under the law that
                                applies, such as liability for death or personal injury caused by negligence, or for
                                fraud.
                            </p>
                        </Section>

                        <Section id="changes" title="Changes to these terms">
                            <p>
                                We may update these terms from time to time, and we publish any changes on this page. If
                                you keep using the website after a change, you accept the updated terms. These terms
                                were last updated on {LAST_UPDATED}.
                            </p>
                        </Section>

                        <Section id="law" title="Governing law">
                            <p>
                                The guild's officers and the website's host live in different countries. Any dispute
                                about these terms involving one of them is governed by the law of the country where that
                                person lives, and the courts of that country have jurisdiction over it.
                            </p>
                            <p>
                                These terms apply as fully as each of those laws allows. If you live elsewhere, you also
                                keep any rights that the law where you live gives you and that can't be removed by
                                agreement. If any part of these terms is found to be unenforceable somewhere, the rest
                                still applies.
                            </p>
                        </Section>
                    </div>
                </div>
            </Band>

            <Band
                tone="shaded"
                edge="both"
                id="contact"
                aria-labelledby="contact-heading"
                maxWidth="max-w-2xl text-center"
            >
                <BandHeading level={2} id="contact-heading" className="mb-4">
                    How to contact us
                </BandHeading>
                <p className="mx-auto mb-8 max-w-xl leading-relaxed text-gray-300">
                    If you have any questions about these terms, or you'd like to report content or behaviour on the
                    website, join our Discord server and ask an officer.
                </p>
                <JoinDiscordButton />
            </Band>
        </Master>
    );
}
