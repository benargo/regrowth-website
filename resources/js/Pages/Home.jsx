import { Deferred, usePage } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import RaggedEdge from "@/Components/Home/RaggedEdge";
import LaunchCountdown from "@/Components/Home/LaunchCountdown";
import OfficerTeam from "@/Components/Home/OfficerTeam";
import UpcomingEvents from "@/Components/Home/UpcomingEvents";
import Section from "@/Components/Home/Section";
import DisplayHeading from "@/Components/Home/DisplayHeading";
import Icon from "@/Components/FontAwesome/Icon";

export default function Home({ foreverLaunchAt, officers, canViewPlans }) {
    const { navigation, upcomingEvents } = usePage().props;
    return (
        <Master title="Home" isHomepage>
            <div className="text-base">
                <div className="flex min-h-svh flex-col lg:h-screen">
                    <section className="bg-masthead-forever relative flex flex-1 flex-col">
                        <div className="hidden h-[60vh] overflow-hidden md:block md:h-[800px] lg:h-full" />

                        <div className="relative flex flex-1 flex-col items-center justify-center py-20 md:absolute md:inset-0 md:py-0">
                            <div className="my-10 flex flex-row items-center">
                                <div className="md:mr-10">
                                    <img
                                        src="/images/guild_emblem.webp"
                                        alt="Regrowth emblem"
                                        width={230}
                                        height={230}
                                        fetchPriority="high"
                                        className="mx-auto h-32 w-auto drop-shadow-[0_0_25px_rgba(0,0,0,0.6)] md:mx-0 md:h-48"
                                    />
                                </div>
                                <div className="text-center md:text-left">
                                    <h1
                                        translate="no"
                                        className="from-camel-100 to-camel-300 mb-3 bg-linear-to-b bg-clip-text pb-1 font-serif text-6xl font-normal text-transparent drop-shadow-[0_2px_12px_rgba(0,0,0,0.8)] text-shadow-sm md:text-8xl"
                                    >
                                        Regrowth
                                    </h1>
                                    <p
                                        translate="no"
                                        className="from-camel-300 to-camel-500 text-camel-400 bg-linear-to-b bg-clip-text text-2xl tracking-[0.2em] uppercase md:text-3xl"
                                    >
                                        Thunderstrike
                                    </p>
                                </div>
                            </div>
                        </div>

                        <RaggedEdge position="bottom" tone="mid" />
                    </section>

                    <LaunchCountdown targetIso={foreverLaunchAt} />
                </div>

                <OfficerTeam officers={officers} />

                <Deferred
                    data="upcomingEvents"
                    fallback={<UpcomingEvents events={undefined} canViewPlans={canViewPlans} />}
                >
                    <UpcomingEvents events={upcomingEvents} canViewPlans={canViewPlans} />
                </Deferred>

                <Section tone="deep" maxWidth="max-w-2xl text-center">
                    <DisplayHeading level={2} eyebrow="Join us" className="mb-4">
                        Your Journey Starts Here
                    </DisplayHeading>

                    <p className="text-camel-400 mx-auto mb-8 max-w-xl">
                        Whether you’re a seasoned raider or stepping into Azeroth for the first time, there’s a place
                        for you in Regrowth. Come and say hello — recruitment, raid chatter and everything else happens
                        on our Discord.
                    </p>

                    {navigation?.discord_invite_url && (
                        <a
                            href={navigation.discord_invite_url}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="border-discord/30 text-camel-200 bg-discord focus-visible:outline-camel-400 inline-flex items-center gap-3 rounded border px-8 py-4 text-lg shadow-lg transition-[filter,box-shadow] hover:shadow-xl hover:brightness-115 focus-visible:outline-2 focus-visible:outline-offset-2"
                        >
                            <Icon icon="discord" style="brands" className="h-6 w-6 text-white" />
                            Join Our Discord
                        </a>
                    )}
                </Section>
            </div>
        </Master>
    );
}
