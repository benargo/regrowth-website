import { Link } from "@inertiajs/react";
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
    { value: "desktop-app", label: "The Regrowth Desktop app" },
    { value: "bans", label: "Guild bans" },
    { value: "times", label: "Times and dates" },
    { value: "how-we-use", label: "How we use your data" },
    { value: "sharing", label: "Who we share it with" },
    { value: "blizzard-api", label: "Blizzard Developer APIs" },
    { value: "storage", label: "Storage and retention" },
    { value: "linked-apps", label: "Linked apps and sign-in" },
    { value: "rights", label: "Your data protection rights" },
    { value: "cookies", label: "Cookies and local storage" },
    { value: "on-your-pc", label: "Data the app keeps on your PC" },
    { value: "other-websites", label: "Other websites" },
    { value: "changes", label: "Changes to this policy" },
    { value: "contact", label: "How to contact us" },
];

const LAST_UPDATED = "15 November 2026";

const INLINE_LINK_CLASS =
    "text-amber-400 underline decoration-amber-700 underline-offset-4 transition-colors hover:text-amber-300 hover:decoration-amber-400 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400";

/**
 * A link to another section of this page.
 */
function SectionLink({ to, children }) {
    return (
        <a href={`#${to}`} className={INLINE_LINK_CLASS}>
            {children}
        </a>
    );
}

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
                                <li>
                                    Guild membership: when a character joined, left or rejoined the guild, and which
                                    characters are alts of the same player
                                </li>
                                <li>
                                    Guild bans recorded by officers (see <SectionLink to="bans">Guild bans</SectionLink>
                                    )
                                </li>
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
                                    The Guild Roster Manager add-on, whose roster data our officers send to us, either
                                    by uploading an export or automatically through the Regrowth Desktop app (see{" "}
                                    <SectionLink to="desktop-app">The Regrowth Desktop app</SectionLink>)
                                </li>
                            </List>

                            <p>
                                We keep data about the characters in the guild even if their players have never visited
                                this website.
                            </p>
                        </Section>

                        <Section id="desktop-app" title="The Regrowth Desktop app">
                            <p>
                                Some of our officers use the Regrowth Desktop app, a small program for Windows that
                                keeps the guild roster on this website up to date. It reads the data that the Guild
                                Roster Manager (GRM) add-on saves on the officer's computer whenever World of Warcraft
                                writes it, and sends us only the changes. This means data about you can reach us if you
                                are in the guild, even if you have never visited this website.
                            </p>
                            <p>
                                The app reads only Regrowth's data. GRM's file also holds data about other guilds the
                                officer has been in, and none of it is read or sent.
                            </p>

                            <p>For each character in the guild, or who has left it, the app sends:</p>
                            <List>
                                <li>Their name, as it appears in the game, and GRM's ID for the character</li>
                                <li>Their guild rank, level, class and race</li>
                                <li>Which alt group they belong to, and which character is the group's main</li>
                                <li>
                                    The dates they joined, left or rejoined the guild. The app sends only dates that GRM
                                    has marked as verified.
                                </li>
                                <li>
                                    When GRM recorded each change, as GRM saved it (see{" "}
                                    <SectionLink to="times">Times and dates</SectionLink>)
                                </li>
                                <li>
                                    Any ban or unban recorded for them (see{" "}
                                    <SectionLink to="bans">Guild bans</SectionLink>)
                                </li>
                            </List>

                            <p>With each sync, it also sends:</p>
                            <List>
                                <li>The guild's rank names, and the guild's name as GRM stores it</li>
                                <li>
                                    The GRM add-on's version, and a fingerprint of the GRM file, so we can tell when the
                                    same file is sent twice
                                </li>
                                <li>
                                    A random ID for the app install, and a name for the device (see{" "}
                                    <SectionLink to="linked-apps">Linked apps and sign-in</SectionLink>)
                                </li>
                            </List>

                            <p>The app never sends:</p>
                            <List>
                                <li>Public notes, officer notes or custom notes</li>
                                <li>Birthdays</li>
                                <li>Nicknames</li>
                                <li>Professions</li>
                                <li>The zone a character is in</li>
                                <li>When a character was last online</li>
                                <li>
                                    Anything from GRM's guild log. The app reads only the dates of its entries, on the
                                    officer's computer, to pick the right GRM file, and never sends them.
                                </li>
                                <li>A character's faction or full rank history</li>
                                <li>Characters below level 10</li>
                            </List>

                            <p>
                                From GRM's settings, the app reads only whether GRM's sync and ban list sync are turned
                                on, and the add-on version. It will not sync while either setting is off.
                            </p>
                            <p>
                                The app never connects to our database. It sends its data to this website, which checks
                                it and checks that the officer is still allowed to send it.
                            </p>
                        </Section>

                        <Section id="bans" title="Guild bans">
                            <p>
                                Officers can record guild bans in GRM. When they do, the Regrowth Desktop app sends us
                                the ban: the character's name and GRM ID, the name of the officer's character who
                                recorded it, when it was recorded, and the reason the officer gave. If the player is
                                later unbanned, it sends that too.
                            </p>
                            <p>
                                We keep these bans as a single record for the guild. Where we can match a ban to a
                                character on this website, we link it to that character and, if the character is linked
                                to a Discord account, to that account.
                            </p>
                            <p>
                                Officers can only send bans if they have permission to. The app skips bans for anyone
                                without it.
                            </p>
                            <p>
                                Bans are never shown on public pages. Only officers given permission to view bans can
                                see the record, and only officers given a separate permission can see the reasons.
                            </p>
                            <p>
                                When someone is unbanned, we record the date. If you think a ban recorded about you is
                                wrong, <SectionLink to="contact">contact an officer</SectionLink>.
                            </p>
                        </Section>

                        <Section id="times" title="Times and dates">
                            <p>
                                GRM records the date and time of each join, leave, rank change, ban and alt group
                                change. It takes the date from the game's calendar and the time from the clock of the
                                officer's computer that recorded it. The app sends those times with each character, alt
                                group and ban.
                            </p>
                            <p>
                                We use these times to work out which data is newest. When two officers send different
                                data about the same character, we keep the newer. When Blizzard's guild roster is newer
                                than what GRM told us, Blizzard's roster corrects it. The app won't sync while the
                                officer's computer clock is more than a minute out from ours, and we ignore any record
                                dated more than a minute later than our own clock.
                            </p>
                            <p>We store:</p>
                            <List>
                                <li>
                                    For each sync, the officer who sent it, which device sent it, and when the sync
                                    started
                                </li>
                                <li>
                                    For each character, when we last had evidence of their rank and level, whether the
                                    rank came from GRM or Blizzard, and when they left the guild
                                </li>
                                <li>
                                    For each character, the periods they were in the guild, from GRM's join and leave
                                    dates
                                </li>
                            </List>
                            <p>
                                We never store when anyone was last online, and the app never reads when the GRM file
                                was last changed.
                            </p>
                        </Section>

                        <Section id="how-we-use" title="How we use your data">
                            <p>We use your data to:</p>
                            <List>
                                <li>Manage the guild roster and ranks</li>
                                <li>
                                    Keep the guild roster accurate and up to date, including who has joined or left the
                                    guild and which characters belong to the same player
                                </li>
                                <li>Keep a record of guild bans, so officers can keep the guild safe</li>
                                <li>Organise raids and other guild events, and track attendance</li>
                                <li>Decide on loot distribution</li>
                                <li>Post event and guild updates to our Discord server</li>
                                <li>Add you to our Discord server, if you ask to join through this website</li>
                                <li>Provide the website's interactive features to guild members</li>
                            </List>

                            <p>
                                We process this data because we have a legitimate interest in running the guild. When
                                you connect your Battle.net account, we rely on your consent. You can withdraw that
                                consent at any time by signing out, which disconnects Battle.net.
                            </p>
                            <p>
                                The same legitimate interest covers the roster data sent by the Regrowth Desktop app,
                                the ban record and the dates and times described above. We use them only to run the
                                guild, and we collect only what we need for that.
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
                                <li>
                                    <ExternalLink href="https://github.com">GitHub, Inc.</ExternalLink> hosts the
                                    Regrowth Desktop app's downloads. The app checks GitHub for updates when it starts
                                    and every four hours, and GitHub receives your IP address when it does.
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
                            <p>
                                When an officer's app tells us about a character we don't know yet, we ask Blizzard's
                                API for that character's profile, to get Blizzard's ID for it and to confirm it is in
                                the guild. We only add the character once Blizzard confirms it. Every hour we also read
                                the guild roster from Blizzard, and where Blizzard and GRM disagree we keep whichever is
                                newer.
                            </p>
                        </Section>

                        <Section id="storage" title="Storage and retention">
                            <p>
                                Your data is stored securely on servers provided by DigitalOcean. We keep it only for as
                                long as we need it:
                            </p>
                            <List>
                                <li>
                                    Character records, the dates characters joined and left the guild, guild bans,
                                    records of each roster sync and the apps you have linked are kept for as long as the
                                    guild needs them to carry out its functions. You can ask us to remove any record
                                    about you.
                                </li>
                                <li>
                                    When an officer's app starts a sync, the changes it sends are held until the sync is
                                    applied, and deleted as soon as it is. If it isn't applied within an hour, or it is
                                    cancelled or rejected, those changes are deleted within the next 10 minutes.
                                </li>
                                <li>
                                    Roster exports uploaded by officers through the website are kept for three months,
                                    apart from the most recent one for each game version.
                                </li>
                                <li>
                                    The guild roster as GRM last reported it is kept for as long as GRM keeps it. When
                                    GRM drops a former member who left long ago, we drop them from that copy too.
                                </li>
                                <li>
                                    The app's sign-in lasts an hour at a time and renews itself. If the app isn't used
                                    for 90 days, you need to sign in again. Cancelled sign-ins are deleted from our
                                    database within a day, and expired ones a week after they expire.
                                </li>
                                <li>Events, and their sign-ups, are deleted automatically one month after they end.</li>
                                <li>Responses from Blizzard's API are cached for no more than 24 hours.</li>
                                <li>
                                    Your Battle.net connection is held only in your current session, and is never saved
                                    to our database.
                                </li>
                                <li>
                                    When you ask to join through this website, we hold your Discord access token in your
                                    current session, encrypted, and use it to add you to our Discord server once you
                                    agree. We remove it from the session before adding you, or when you decline. If you
                                    leave without answering, it's removed when your session expires. The token is never
                                    saved to your account.
                                </li>
                                <li>
                                    Your Discord account details, planned absences and comments are kept for as long as
                                    you're involved with the guild. If you delete a planned absence or comment, it is
                                    hidden immediately but stays in our database until an officer permanently removes
                                    it. You can ask us to do this at any time.
                                </li>
                            </List>
                        </Section>

                        <Section id="linked-apps" title="Linked apps and sign-in">
                            <p>
                                To link the Regrowth Desktop app, it opens this website in your browser. You sign in
                                with Discord as usual and approve the app, and your browser passes the sign-in back to
                                the app through an address on your own computer (127.0.0.1) that never leaves it. The
                                app's sign-in can only send roster data and read which rosters to watch. It can't act as
                                you anywhere else on the website.
                            </p>
                            <p>
                                Your sign-in is encrypted on your computer with Windows' own data protection. The app
                                refuses to save it if encryption isn't available.
                            </p>
                            <p>
                                For each linked app we record a random ID the app created when it was installed, a name
                                for the device, your account, when it was last used and last synced, and which sign-in
                                it holds. The sign-in is tied to that app, so it can't be used by another install. You
                                can link up to 10 apps.
                            </p>
                            <p>
                                You can see the apps you have linked on your{" "}
                                <Link href={route("account.index")} className={INLINE_LINK_CLASS}>
                                    Account page
                                </Link>
                                , and sign out of any of them, or all of them at once. Signing out cancels the app's
                                sign-in straight away. Officers with permission can also sign out any linked app, and if
                                an officer loses permission to use the app, it stops working at its next sync.
                            </p>
                        </Section>

                        <Section id="rights" title="Your data protection rights">
                            <p>You have the following rights over your data:</p>
                            <TermList terms={RIGHTS} />
                            <p>
                                We will respond to any request within one month. To exercise any of these rights, please{" "}
                                <SectionLink to="contact">contact an officer</SectionLink>.
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

                        <Section id="on-your-pc" title="Data the app keeps on your PC">
                            <p>
                                If you're an officer and use the Regrowth Desktop app, it keeps a small amount of data
                                in your Windows user profile:
                            </p>
                            <List>
                                <li>
                                    A random ID for this install, the World of Warcraft folder, and which GRM files it
                                    watches
                                </li>
                                <li>
                                    When it last synced, a fingerprint of each GRM file, and GRM's ID for each character
                                    and ban it last synced with a fingerprint of that record, so it can send only what
                                    has changed. A fingerprint is a one-way hash, not a copy of the data.
                                </li>
                                <li>
                                    A short list of syncs to try again, holding only the file's location and
                                    fingerprint, never its contents
                                </li>
                                <li>Your encrypted sign-in</li>
                                <li>
                                    A log file of up to about 4 MB, with sign-in details masked and no character names
                                </li>
                            </List>
                            <p>
                                Apart from those IDs, the app keeps no copy of the roster data it reads or sends. Any
                                temporary files it makes while syncing are deleted when each sync finishes, whether it
                                works or not, and anything left over after a crash is deleted when the app next starts.
                            </p>
                            <p>
                                "Copy diagnostics" copies only the app version, your Windows version, the sync status,
                                row counts and the GRM version. Nothing is sent to us until you choose to paste it
                                somewhere.
                            </p>
                            <p>
                                Uninstalling the app leaves its settings and log in your Windows user profile, in the
                                Regrowth Desktop folder under AppData. You can delete that folder to remove them.
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
