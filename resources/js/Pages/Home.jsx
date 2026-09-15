import { Deferred, usePage } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import RaggedEdge from "@/Components/Forever/RaggedEdge";
import LaunchCountdown from "@/Components/Forever/LaunchCountdown";
import OfficerTeam from "@/Components/Forever/OfficerTeam";
import UpcomingEvents from "@/Components/Forever/UpcomingEvents";
import Section from "@/Components/Forever/Section";
import DisplayHeading from "@/Components/Forever/DisplayHeading";
import Icon from "@/Components/FontAwesome/Icon";

function OfficerTeamWithRenders({ officers }) {
    const { officerRenders } = usePage().props;

    return <OfficerTeam officers={officers} renders={officerRenders} />;
}

function UpcomingEventsWithData({ canViewPlans }) {
    const { upcomingEvents } = usePage().props;

    return <UpcomingEvents events={upcomingEvents} canViewPlans={canViewPlans} />;
}

export default function Home({ foreverLaunchAt, officers, canViewPlans, discordInviteUrl }) {
    return (
        <Master title="Home">
            <div className="text-base">
                <section className="bg-forever-masthead relative">
                    <div className="bg-forever-masthead hidden h-[80vh] overflow-hidden md:block md:h-[800px]">
                        {/* <video
                                    preload="auto"
                                    className="h-full w-full object-cover"
                                    playsInline
                                    autoPlay
                                    muted
                                    loop
                                    aria-hidden="true"
                                >
                                    <source src="/videos/bcc_masthead_1.webm" type="video/webm" />
                                    <source src="/videos/bcc_masthead_1.mp4" type="video/mp4" />
                                </video> */}
                    </div>

                    {/* Scrim: darkens the foot of the video so the wordmark and the
                                torn edge stay legible over any frame. */}
                    <div
                        aria-hidden="true"
                        className="from-forever-900 via-forever-900/40 absolute inset-0 bg-gradient-to-t to-transparent"
                    />

                    <div className="relative flex flex-col items-center justify-center py-20 md:absolute md:inset-0 md:py-0">
                        <div className="my-10 flex flex-row items-center">
                            <div className="md:mr-10">
                                <img
                                    src="/images/guild_emblem.webp"
                                    alt="Regrowth emblem"
                                    className="mx-auto h-32 drop-shadow-[0_0_25px_rgba(0,0,0,0.6)] md:mx-0 md:h-48"
                                />
                            </div>
                            <div className="text-center md:text-left">
                                <h1 className="text-camel-200 mb-4 font-serif text-6xl font-normal drop-shadow-[0_2px_12px_rgba(0,0,0,0.8)] md:text-8xl">
                                    Regrowth
                                </h1>
                                <p className="text-camel-400 text-2xl tracking-[0.2em] uppercase md:text-3xl">
                                    Thunderstrike
                                </p>
                            </div>
                        </div>
                    </div>

                    <RaggedEdge position="bottom" tone="mid" />
                </section>

                <LaunchCountdown targetIso={foreverLaunchAt} />

                {/*
                 * officerRenders is deferred: the section paints immediately
                 * with silhouettes and swaps in real renders on resolve.
                 * OfficerTeam handles an undefined map, so the fallback is
                 * the section itself rather than a separate skeleton.
                 */}
                <Deferred data="officerRenders" fallback={<OfficerTeam officers={officers} renders={undefined} />}>
                    <OfficerTeamWithRenders officers={officers} />
                </Deferred>

                <Deferred
                    data="upcomingEvents"
                    fallback={<UpcomingEvents events={undefined} canViewPlans={canViewPlans} />}
                >
                    <UpcomingEventsWithData canViewPlans={canViewPlans} />
                </Deferred>

                <Section tone="deep" className="py-16 md:py-24">
                    <div className="container mx-auto max-w-2xl px-4 text-center">
                        <DisplayHeading level={2} eyebrow="Join us" className="mb-4">
                            Your Journey Starts Here
                        </DisplayHeading>

                        <p className="text-camel-400 mx-auto mb-8 max-w-xl">
                            Whether you're a seasoned raider or stepping into Azeroth for the first time, there's a
                            place for you in Regrowth. Come and say hello — recruitment, raid chatter and everything
                            else happens on our Discord.
                        </p>

                        <a
                            href={discordInviteUrl}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="border-primary/30 text-camel-200 focus:ring-primary/60 inline-flex items-center gap-3 rounded border bg-[#5865F2] px-8 py-4 text-lg shadow-lg transition-colors hover:bg-[#5865F2]/80 focus:ring-2 focus:outline-hidden"
                        >
                            <Icon icon="discord" style="brands" className="h-6 w-6 text-white" />
                            Join our Discord
                        </a>
                    </div>
                </Section>
            </div>
        </Master>
    );
}
