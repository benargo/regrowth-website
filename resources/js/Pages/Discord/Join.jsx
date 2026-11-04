import { useState } from "react";
import { Head, useForm } from "@inertiajs/react";
import Master from "@/Layouts/Master";
import Band from "@/Components/Band/Band";
import BandHeading from "@/Components/Band/BandHeading";
import Icon from "@/Components/FontAwesome/Icon";

/**
 * Asks someone who has signed in with Discord whether we may add them to the
 * Regrowth Discord server. Agreeing adds them; declining returns them home.
 */
export default function Join() {
    const form = useForm();
    const [isConfirming, setIsConfirming] = useState(false);

    const confirm = () => {
        setIsConfirming(true);
        form.post(route("discord.store"));
    };

    const decline = () => {
        setIsConfirming(false);
        form.delete(route("discord.destroy"));
    };

    return (
        <Master title="Join our Discord">
            <Head>
                <meta name="robots" content="noindex, nofollow" />
            </Head>
            <Band className="flex flex-1 items-center text-center" maxWidth="max-w-2xl">
                <img
                    src="/images/jaina_thumbsup.webp"
                    width={1024}
                    height={1024}
                    alt=""
                    className="mx-auto mt-8 mb-6 h-48 w-48 md:h-64 md:w-64"
                />
                <BandHeading level={1} className="mb-4">
                    Join our Discord
                </BandHeading>
                <p className="mx-auto mb-8 max-w-xl leading-relaxed text-gray-300">
                    May we add you to the Regrowth Discord server using your Discord account? It’s your choice, and we
                    won’t add you unless you say yes.
                </p>
                <div className="flex flex-wrap items-center justify-center gap-6">
                    <button
                        type="button"
                        onClick={confirm}
                        disabled={form.processing}
                        className="bg-discord border-discord/30 inline-flex items-center gap-3 rounded border px-8 py-4 text-lg text-white shadow-lg transition-[filter,box-shadow] hover:shadow-xl hover:brightness-90 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        <Icon icon="discord" style="brands" className="h-6 w-6" />
                        {form.processing && isConfirming ? "Adding you…" : "Yes, add me"}
                    </button>
                    <button
                        type="button"
                        onClick={decline}
                        disabled={form.processing}
                        className="text-gray-400 hover:text-gray-200 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-amber-400 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        No thanks
                    </button>
                </div>
            </Band>
        </Master>
    );
}
