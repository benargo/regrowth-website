import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import OnThisPage from "@/Components/OnThisPage";
import ExternalLink from "@/Components/ExternalLink";
import Band from "@/Components/Band/Band";
import BandHeading from "@/Components/Band/BandHeading";
import JoinDiscordButton from "@/Components/Discord/JoinDiscordButton";

const SECTIONS = [
    { value: "about", label: "About this policy" },
    { value: "data-we-collect", label: "What data we collect" },
    { value: "how-we-collect", label: "How we collect your data" },
    { value: "how-we-use", label: "How we use your data" },
    { value: "sharing", label: "Who we share it with" },
    { value: "blizzard-api", label: "Blizzard Developer APIs" },
    { value: "storage", label: "Storage and retention" },
    { value: "rights", label: "Your data protection rights" },
    { value: "cookies", label: "Cookies and local storage" },
    { value: "other-websites", label: "Other websites" },
    { value: "changes", label: "Changes to this policy" },
    { value: "contact", label: "How to contact us" },
];

const LAST_UPDATED = "29 October 2026";

/**
 * A titled policy section, labelled by its heading.
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

const RIGHTS = [
    {
        term: "The right of access",
        description: "You can ask us for a copy of the personal data we hold about you, free of charge.",
    },
    {
        term: "The right to rectification",
        description:
            "You can ask us to correct data you think is inaccurate, or to complete data you think is incomplete.",
    },
    {
        term: "The right to erasure",
        description: "You can ask us to erase your personal data, under certain conditions.",
    },
    {
        term: "The right to restrict processing",
        description: "You can ask us to restrict how we process your personal data, under certain conditions.",
    },
    {
        term: "The right to object to processing",
        description: "You can object to how we process your personal data, under certain conditions.",
    },
    {
        term: "The right to data portability",
        description:
            "You can ask us to transfer the data we've collected to another organisation, or directly to you, under certain conditions.",
    },
];

const COOKIES = [
    {
        term: "Session cookie",
        description: "This identifies your visit and keeps you signed in while you browse.",
    },
    {
        term: "Remember-me cookie",
        description: "This keeps you signed in between visits until you sign out.",
    },
    {
        term: "XSRF-TOKEN cookie",
        description: "This protects the forms on the website against cross-site request forgery.",
    },
];

export default function PrivacyPolicy() {
    return (
        <Master title="Privacy Policy">
            <SharedHeader
                title="Privacy Policy"
                subtitle={`Last updated ${LAST_UPDATED}`}
                backgroundClass="bg-undercity-bank"
            />

            <Band tone="light">
                <div className="flex flex-col gap-10 lg:flex-row lg:items-start lg:gap-16">
                    <OnThisPage sections={SECTIONS} className="hidden lg:flex lg:w-1/4 lg:shrink-0" />

                    <div className="flex max-w-prose min-w-0 flex-1 flex-col gap-14 leading-relaxed text-gray-300">
                        <Section id="about" title="About this policy">
                            <p className="text-lg text-amber-100">
                                This privacy policy explains what personal data Regrowth collects about you, how we use
                                it, and the rights you have over it. Regrowth is a World of Warcraft guild run by
                                volunteers, not a company, but we take data protection seriously and aim to follow the
                                UK GDPR. The guild's officers are responsible for the data described here.
                            </p>
                        </Section>

                        <Section id="data-we-collect" title="What data we collect">
                            <p>When you sign in with Discord, we store:</p>
                            <List>
                                <li>Your Discord user ID, username and discriminator</li>
                                <li>Your Discord display name and nickname in our server</li>
                                <li>Your Discord avatar and your server-specific avatar</li>
                                <li>The roles you hold in our Discord server</li>
                            </List>

                            <p>About the World of Warcraft characters in the guild, we store:</p>
                            <List>
                                <li>Character name, realm, level, class, race and gender</li>
                                <li>Guild rank</li>
                                <li>Which Discord account a character belongs to, where known</li>
                                <li>Raid attendance and performance taken from Warcraft Logs reports</li>
                            </List>

                            <p>When you use the website's features, we store what you give us, including:</p>
                            <List>
                                <li>Event sign-ups and attendance</li>
                                <li>Planned absences, including their dates, reason and any notes you add</li>
                                <li>Comments you post, along with their edit history</li>
                            </List>
                        </Section>

                        <Section id="how-we-collect" title="How we collect your data">
                            <p>You give us most of this data directly when you:</p>
                            <List>
                                <li>Sign in to this website with your Discord account</li>
                                <li>Connect your Battle.net account to choose your characters</li>
                                <li>Sign up for events, record a planned absence, or post a comment</li>
                            </List>

                            <p>We also receive data indirectly from:</p>
                            <List>
                                <li>Discord, Inc. (your profile and server roles)</li>
                                <li>Blizzard Entertainment, Inc. (the guild roster and character profiles)</li>
                                <li>Warcraft Logs (raid combat logs uploaded by guild members)</li>
                                <li>Raid-Helper (event sign-ups made through our Discord server)</li>
                                <li>
                                    The Guild Roster Manager add-on, whose roster exports are uploaded by our officers
                                </li>
                            </List>

                            <p>
                                We keep data about the characters in the guild even if their players have never visited
                                this website.
                            </p>
                        </Section>

                        <Section id="how-we-use" title="How we use your data">
                            <p>We use your data to:</p>
                            <List>
                                <li>Manage the guild roster and ranks</li>
                                <li>Organise raids and other guild events, and track attendance</li>
                                <li>Decide on loot distribution</li>
                                <li>Post event and guild updates to our Discord server</li>
                                <li>Provide the website's interactive features to guild members</li>
                            </List>

                            <p>
                                We process this data because we have a legitimate interest in running the guild. When
                                you connect your Battle.net account, we rely on your consent. You can withdraw that
                                consent at any time by signing out, which disconnects Battle.net.
                            </p>
                        </Section>

                        <Section id="sharing" title="Who we share it with">
                            <p>
                                Regrowth never sells your data or shares it with other companies so they can market
                                their products and services to you. We do not use advertising or analytics services.
                            </p>
                            <p>To run the website, we rely on these third parties:</p>
                            <List>
                                <li>
                                    <ExternalLink href="https://m.do.co/c/d0af7c248cc4">DigitalOcean, LLC</ExternalLink>{" "}
                                    hosts our servers, database and file storage.
                                </li>
                                <li>
                                    Discord, Inc. receives the messages we post to our Discord server, which may mention
                                    guild members.
                                </li>
                                <li>
                                    <ExternalLink href="https://www.wowhead.com">Wowhead</ExternalLink> provides the
                                    item and spell tooltips, using a script that runs on every page. Wowhead receives
                                    your IP address and browser information when it loads.
                                </li>
                                <li>
                                    <ExternalLink href="https://fonts.adobe.com">Adobe Fonts</ExternalLink> serves the
                                    website's fonts. Adobe receives your IP address and browser information when they
                                    load.
                                </li>
                            </List>
                        </Section>

                        <Section id="blizzard-api" title="Blizzard Developer APIs">
                            <p>
                                Some of Regrowth's services use data from the Blizzard Developer APIs. That data is
                                subject to the{" "}
                                <ExternalLink href="https://www.blizzard.com/en-us/legal/a2989b50-5f16-43b1-abec-2ae17cc09dd6/blizzard-developer-api-terms-of-use">
                                    Blizzard Developer API Terms of Use
                                </ExternalLink>
                                .
                            </p>
                            <p>
                                Our use of this data is covered by this privacy policy and by the{" "}
                                <ExternalLink href="https://www.blizzard.com/en-us/legal/a4380ee5-5c8d-4e3b-83b7-ea26d01a9918/blizzard-entertainment-online-privacy-policy">
                                    Blizzard Entertainment, Inc. privacy policy
                                </ExternalLink>
                                . You have the right to decide how Blizzard processes your personal data, and to object
                                to providing us with information through the API.
                            </p>
                        </Section>

                        <Section id="storage" title="Storage and retention">
                            <p>
                                Your data is stored securely on servers provided by DigitalOcean. We keep it only for as
                                long as we need it:
                            </p>
                            <List>
                                <li>
                                    Character records that haven't been refreshed from Blizzard for 14 days, for example
                                    after a character leaves the guild, are deleted automatically.
                                </li>
                                <li>Events, and their sign-ups, are deleted automatically one month after they end.</li>
                                <li>Responses from Blizzard's API are cached for no more than 24 hours.</li>
                                <li>
                                    Your Battle.net connection is held only in your current session, and is never saved
                                    to our database.
                                </li>
                                <li>
                                    Your Discord account details, planned absences and comments are kept for as long as
                                    you're involved with the guild. If you delete a planned absence or comment, it is
                                    hidden immediately but stays in our database until an officer permanently removes
                                    it. You can ask us to do this at any time.
                                </li>
                            </List>
                        </Section>

                        <Section id="rights" title="Your data protection rights">
                            <p>You have the following rights over your data:</p>
                            <TermList terms={RIGHTS} />
                            <p>
                                We will respond to any request within one month. To exercise any of these rights, please{" "}
                                <a
                                    href="#contact"
                                    className="text-amber-400 underline decoration-amber-700 underline-offset-4 transition-colors hover:text-amber-300 hover:decoration-amber-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400"
                                >
                                    contact an officer
                                </a>
                                .
                            </p>
                            <p>
                                If you're unhappy with how we've handled your data, you can complain to the{" "}
                                <ExternalLink href="https://ico.org.uk/make-a-complaint/">
                                    Information Commissioner's Office
                                </ExternalLink>
                                .
                            </p>
                        </Section>

                        <Section id="cookies" title="Cookies and local storage">
                            <p>
                                Cookies are small text files that a website saves in your browser. We use only the
                                cookies needed for the website to work:
                            </p>
                            <TermList terms={COOKIES} />
                            <p>
                                The website also uses your browser's local and session storage to remember display
                                preferences, such as roster filters and which panels you've expanded. This data never
                                leaves your device.
                            </p>
                            <p>
                                Wowhead and Adobe Fonts may set their own cookies. Their privacy policies cover how they
                                use them.
                            </p>
                            <p>
                                You can set your browser to block or delete cookies, but you won't be able to sign in
                                without them. For more information, visit{" "}
                                <ExternalLink href="https://allaboutcookies.org">allaboutcookies.org</ExternalLink>.
                            </p>
                        </Section>

                        <Section id="other-websites" title="Other websites">
                            <p>
                                This website links to other websites. Our privacy policy applies only to this website,
                                so if you follow a link elsewhere, please read that website's privacy policy.
                            </p>
                        </Section>

                        <Section id="changes" title="Changes to this policy">
                            <p>
                                We keep this privacy policy under regular review, and we publish any updates on this
                                page. This privacy policy was last updated on {LAST_UPDATED}.
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
                    If you have any questions about this privacy policy or the data we hold about you, or you'd like to
                    exercise any of your data protection rights, join our Discord server and ask an officer.
                </p>
                <JoinDiscordButton />
            </Band>
        </Master>
    );
}
