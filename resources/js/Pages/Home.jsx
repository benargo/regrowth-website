import { Link } from '@inertiajs/react';
import Master from '@/Layouts/Master';
import ClassIcon from '@/Components/ClassIcon';

export default function Home({ recruiting_classes = [] }) {
    return (
        <Master title="Home">
            <div className="page-home text-base">
                {/* Hero Section with Video Background */}
                <div className="bg-masthead md:bg-masthead-desktop relative">
                    <div className="hidden md:block overflow-hidden h-[80vh] md:h-[800px]">
                        <video
                            preload="auto"
                            className="w-full h-full object-cover"
                            playsInline
                            autoPlay
                            muted
                            loop
                        >
                            <source src="/videos/bcc_masthead_1.webm" type="video/webm" />
                            <source src="/videos/bcc_masthead_1.mp4" type="video/mp4" />
                        </video>
                    </div>
                    <div className="md:absolute md:inset-0 md:flex items-center justify-center bg-black/50 md:h-[800px] py-20 md:py-0">
                        <div className="md:mr-10">
                            <img src="/images/guild_emblem.webp" alt="Regrowth emblem" className="h-32 md:h-48 mx-auto md:mx-0" />
                        </div>
                        <div className="text-center md:text-left text-white">
                            <h1 className="text-6xl md:text-8xl font-bold mb-4">
                                Regrowth
                            </h1>
                            <h2 className="text-2xl md:text-3xl">
                                Thunderstrike
                            </h2>
                        </div>
                    </div>
                </div>
            </div>
        </Master>
    );
}
