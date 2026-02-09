import Master from "@/Layouts/Master";
import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import SharedHeader from "@/Components/SharedHeader";

function headerInner() {
    return (
        <div className="flex flex-row items-center justify-center gap-4">
            <Icon icon="user-secret" style="solid" className="text-4xl" />
            <span className="text-4xl font-bold">Privacy Policy</span>
        </div>
    );
}

export default function PrivacyPolicy() {
    return (
        <Master title="Privacy Policy">
            <SharedHeader backgroundClass="bg-ironforge" title={headerInner()} />
            <div className="py-12 text-white">
                <main className="container mx-auto">
                    <p className="font-md mb-2 text-gray-400">
                        This privacy policy will explain how Regrowth uses the personal data we collect from you. While
                        we need to be GDPR compliant, and take data protection seriously, please bear in mind that
                        Regrowth is a World of Warcraft guild, not an organisation.
                    </p>

                    <h2 className="font-lg mb-2 text-gray-400">Topics:</h2>
                    <ol className="mb-4 list-inside list-decimal text-gray-400">
                        <li>
                            <a href="#1">What data do we collect?</a>
                        </li>
                        <li>
                            <a href="#2">How do we collect your data?</a>
                        </li>
                        <li>
                            <a href="#3">How will we use your data?</a>
                        </li>
                        <li>
                            <a href="#4">How do we store your data?</a>
                        </li>
                        <li>
                            <a href="#5">What are your data protection rights?</a>
                        </li>
                        <li>
                            <a href="#6">What are cookies?</a>
                        </li>
                        <li>
                            <a href="#7">How do we use cookies?</a>
                        </li>
                        <li>
                            <a href="#8">What types of cookies do we use?</a>
                        </li>
                        <li>
                            <a href="#9">How to manage your cookies</a>
                        </li>
                        <li>
                            <a href="#10">Privacy policies of other websites</a>
                        </li>
                        <li>
                            <a href="#11">Changes to our privacy policy</a>
                        </li>
                        <li>
                            <a href="#12">How to contact us</a>
                        </li>
                    </ol>

                    <a name="1"></a>
                    <h2 className="font-lg mb-2 text-gray-400">What data do we collect?</h2>
                    <p className="font-md mb-2 text-gray-400">Regrowth collects the following data:</p>

                    <ul className="mb-4 list-inside list-disc text-gray-400">
                        <li>
                            Personal identification information (Name, Blizzard Battle.net Battletag, Discord username,
                            etc.)
                        </li>
                        <li>World of Warcraft character information</li>
                    </ul>

                    <a name="2"></a>
                    <h2 className="font-lg mb-2 text-gray-400">How do we collect your data?</h2>
                    <p className="font-md mb-2 text-gray-400">
                        You directly provide Regrowth with most of the data we collect. We collect data and process data
                        when you:
                    </p>

                    <ul className="mb-4 list-inside list-disc text-gray-400">
                        <li>Use or view our website via your browser's cookies</li>
                        <li>Sign in to this website using your Discord account</li>
                        <li>Apply to join Regrowth</li>
                        <li>
                            Use any of the interactive features available on this website (e.g. guild bank, marketplace,
                            events calendar, etc.)
                        </li>
                    </ul>

                    <p className="font-md mb-2 text-gray-400">
                        Regrowth may also receive your data indirectly from the following sources:
                    </p>

                    <ul className="mb-4 list-inside list-disc text-gray-400">
                        <li>Blizzard Entertainment, Inc.</li>
                        <li>Discord, Inc.</li>
                        <li>Warcraft Logs</li>
                    </ul>

                    <a name="3"></a>
                    <h2 className="font-lg mb-2 text-gray-400">How will we use your data?</h2>
                    <p className="font-md mb-2 text-gray-400">Regrowth collects your data so that we can:</p>

                    <ul className="mb-4 list-inside list-disc text-gray-400">
                        <li>Manage the guild roster</li>
                        <li>Organise guild events</li>
                        <li>Offer interactive services to enhance your experience as a member of the guild</li>
                    </ul>

                    <p className="font-md mb-2 text-gray-400">
                        Regrowth will never share your data with other companies so that they may offer you their
                        products and services.
                    </p>

                    <a name="4"></a>
                    <h2 className="font-lg mb-2 text-gray-400">How do we store your data?</h2>
                    <p className="font-md mb-2 text-gray-400">
                        Regrowth securely stores your data on virtual private servers provided by{" "}
                        <a href="https://m.do.co/c/d0af7c248cc4">DigitalOcean, LLC.</a>.
                    </p>

                    <p className="font-md mb-2 text-gray-400">
                        Regrowth will keep your data for between 30 days and two 2 years. Once this time period has
                        expired, it will be automatically removed from our databases. All data shared with us from
                        Blizzard Entertainment, Inc. is subject to a 30-day TTL (time-to-live) policy, meaning we retain
                        this data for no longer than 30 days.
                    </p>

                    <a name="5"></a>
                    <h2 className="font-lg mb-2 text-gray-400">What are your data protection rights?</h2>

                    <p className="font-md mb-2 text-gray-400">
                        Regrowth would like to make sure you are fully aware of all of your data protection rights.
                        Every user is entitled to the following:
                    </p>

                    <ul className="mb-4 list-inside list-disc text-gray-400">
                        <li>
                            The right to access – You have the right to request Regrowth for copies of your personal
                            data. We may charge you a small fee for this service.
                        </li>
                        <li>
                            The right to rectification – You have the right to request that Regrowth correct any
                            information you believe is inaccurate. You also have the right to request Regrowth to
                            complete the information you believe is incomplete.
                        </li>
                        <li>
                            The right to erasure – You have the right to request that Regrowth erase your personal data,
                            under certain conditions.
                        </li>
                        <li>
                            The right to restrict processing – You have the right to request that Regrowth restrict the
                            processing of your personal data, under certain conditions.
                        </li>
                        <li>
                            The right to object to processing – You have the right to object to Regrowth's processing of
                            your personal data, under certain conditions.
                        </li>
                        <li>
                            The right to data portability – You have the right to request that Regrowth transfer the
                            data that we have collected to another organization, or directly to you, under certain
                            conditions.
                        </li>
                    </ul>

                    <p className="font-md mb-2 text-gray-400">
                        If you make a request, we have one month to respond to you. If you would like to exercise any of
                        these rights, please contact an officer.
                    </p>

                    <a name="6"></a>
                    <h2 className="font-lg mb-2 text-gray-400">Cookies</h2>
                    <p className="font-md mb-2 text-gray-400">
                        Cookies are text files placed on your computer to collect standard Internet log information and
                        visitor behavior information. When you visit our websites, we may collect information from you
                        automatically through cookies or similar technology. For further information, visit{" "}
                        <a href="http://allaboutcookies.org">allaboutcookies.org</a>.
                    </p>

                    <a name="7"></a>
                    <h3 className="font-md mb-2 text-gray-400">How do we use cookies?</h3>
                    <p className="font-md mb-2 text-gray-400">
                        Regrowth uses cookies in a range of ways to improve your experience on our website, including:
                    </p>

                    <ul className="mb-4 list-inside list-disc text-gray-400">
                        <li>Keeping you signed in</li>
                        <li>Understanding how you use our website</li>
                    </ul>

                    <a name="8"></a>
                    <h3 className="font-md mb-2 text-gray-400">What types of cookies do we use?</h3>
                    <p className="font-md mb-2 text-gray-400">
                        There are a number of different types of cookies, however, our website uses:
                    </p>

                    <ul className="mb-4 list-inside list-disc text-gray-400">
                        <li>
                            Functionality – Regrowth uses these cookies so that we recognize you on our website and
                            remember your previously selected preferences. These could include what language you prefer
                            and location you are in. A mix of first-party and third-party cookies are used.
                        </li>
                        <li>
                            Advertising – Regrowth uses these cookies to collect information about your visit to our
                            website, the content you viewed, the links you followed and information about your browser,
                            device, and your IP address. Regrowth sometimes shares some limited aspects of this data
                            with third parties for advertising purposes. We may also share online data collected through
                            cookies with our advertising partners. This means that when you visit another website, you
                            may be shown advertising based on your browsing patterns on our website.
                        </li>
                    </ul>

                    <a name="9"></a>
                    <h3 className="font-md mb-2 text-gray-400">How to manage cookies</h3>
                    <p className="font-md mb-2 text-gray-400">
                        You can set your browser not to accept cookies, and the above website tells you how to remove
                        cookies from your browser. However, in a few cases, some of our website features may not
                        function as a result.
                    </p>

                    <a name="10"></a>
                    <h2 className="font-lg mb-2 text-gray-400">Privacy policies of other websites</h2>
                    <p className="font-md mb-2 text-gray-400">
                        This website contains links to other websites. Our privacy policy applies only to our website,
                        so if you click on a link to another website, you should read their privacy policy.
                    </p>

                    <a name="11"></a>
                    <h2 className="font-lg mb-2 text-gray-400">Changes to our privacy policy</h2>
                    <p className="font-md mb-2 text-gray-400">
                        Regrowth keeps its privacy policy under regular review and places any updates on this web page.
                        This privacy policy was last updated on 28 October 2019.
                    </p>

                    <a name="12"></a>
                    <h2 className="font-lg mb-2 text-gray-400">How to contact us</h2>
                    <p className="font-md mb-2 text-gray-400">
                        If you have any questions about Regrowth's privacy policy, the data we hold on you, or you would
                        like to exercise one of your data protection rights, please do not hesitate to contact us.
                    </p>

                    <p className="font-md mb-2 text-gray-400">
                        Join our{" "}
                        <Link
                            href="https://discord.gg/regrowth"
                            target="_blank"
                            rel="noopener noreferrer"
                            className="font-bold text-amber-400 transition-colors hover:text-amber-300"
                        >
                            Discord
                        </Link>{" "}
                        server and ask an officer.
                    </p>
                </main>
            </div>
        </Master>
    );
}
