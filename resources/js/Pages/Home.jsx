import Master from "@/Layouts/Master";
import Podium from "@/Components/Podium";

export default function Home({ members }) {
    return (
        <Master title="Home">
            <div className="text-base">
                {/* Hero Section with Video Background */}
                <div className="bg-masthead md:bg-masthead-desktop relative">
                    <div className="hidden h-[80vh] overflow-hidden md:block md:h-[800px]">
                        <video preload="auto" className="h-full w-full object-cover" playsInline autoPlay muted loop>
                            <source src="/videos/bcc_masthead_1.webm" type="video/webm" />
                            <source src="/videos/bcc_masthead_1.mp4" type="video/mp4" />
                        </video>
                    </div>
                    <div className="items-center justify-center bg-black/20 py-20 md:absolute md:inset-0 flex flex-col md:py-0">
                        <div className="flex flex-row items-center my-10">
                            <div className="md:mr-10">
                                <img
                                    src="/images/guild_emblem.webp"
                                    alt="Regrowth emblem"
                                    className="mx-auto h-32 md:mx-0 md:h-48"
                                />
                            </div>
                            <div className="text-center text-white md:text-left">
                                <h1 className="mb-4 text-6xl font-bold md:text-8xl">Regrowth</h1>
                                <h2 className="text-2xl md:text-3xl">Thunderstrike</h2>
                            </div>
                        </div>
                    </div>
                </div>

                <Podium />
            </div>
        </Master>
    );
}
