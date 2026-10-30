import { Deferred, usePage } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import RaggedEdge from "@/Components/Home/RaggedEdge";
import LaunchCountdown from "@/Components/Home/LaunchCountdown";
import OfficerTeam from "@/Components/Home/OfficerTeam";
import UpcomingEvents from "@/Components/Home/UpcomingEvents";
import HistoryTimeline from "@/Components/Home/HistoryTimeline";
import Section from "@/Components/Home/Section";
import DisplayHeading from "@/Components/Home/DisplayHeading";
import JoinDiscordButton from "@/Components/Discord/JoinDiscordButton";

export default function Home({ foreverLaunchAt, officers, canViewPlans }) {
    const { upcomingEvents } = usePage().props;
    return (
        <Master title="Home" isHomepage>
            <div className="text-base">
                <div className="flex min-h-svh flex-col lg:h-screen">
                    <section className="bg-masthead-forever relative flex flex-1 flex-col">
                        <div className="hidden h-[60vh] overflow-hidden md:block md:h-[800px] lg:h-full" />

                        <div className="relative flex flex-1 flex-col items-center justify-center py-20 md:absolute md:inset-0 md:py-0">
                            <div className="my-10 flex flex-col items-center gap-4 md:flex-row md:gap-10">
                                <img
                                    src="/images/guild_emblem.webp"
                                    alt="Regrowth emblem"
                                    width={230}
                                    height={230}
                                    fetchPriority="high"
                                    className="h-36 w-auto drop-shadow-[0_0_25px_rgba(0,0,0,0.6)] md:h-48"
                                />
                                <h1
                                    translate="no"
                                    className="from-camel-100 to-camel-300 bg-linear-to-b bg-clip-text -my-[0.2em] py-[0.2em] text-center font-serif text-6xl font-normal text-transparent drop-shadow-[0_2px_12px_rgba(0,0,0,0.8)] text-shadow-sm md:text-left md:text-8xl"
                                >
                                    Regrowth
                                </h1>
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

                <HistoryTimeline />

                <Section tone="mid" maxWidth="max-w-2xl text-center">
                    <DisplayHeading level={2} eyebrow="Join us" className="mb-4">
                        Your Journey Starts Here
                    </DisplayHeading>

                    <p className="text-camel-400 mx-auto mb-8 max-w-xl text-pretty">
                        Whether you’re a seasoned raider or stepping into Azeroth for the first time, there’s a place
                        for you in Regrowth. Come and say hello — recruitment, raid chatter and everything else happens
                        on our Discord.
                    </p>

                    <JoinDiscordButton />
                </Section>
            </div>
        </Master>
    );
}
