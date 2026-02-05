import Master from "@/Layouts/Master";
import { Link } from "@inertiajs/react";
import Icon from "@/Components/FontAwesome/Icon";
import SharedHeader from "@/Components/SharedHeader";

function headerInner() {
    return (
        <div className="flex flex-row items-center gap-4">
            <Icon icon="battle-net" style="brands" className="text-4xl" />
            <span className="text-4xl font-bold">Usage of the Blizzard Developer APIs</span>
        </div>
    );
}

export default function BattlenetUsage() {
    return (
        <Master title="Usage of the Blizzard Developer APIs">
            <SharedHeader backgroundClass="bg-ironforge" title={headerInner()}/>
            <div className="py-12 text-white">
                <main className="container mx-auto px-4">
                    <p>
                        Some of the services provided by Regrowth use data obtained from the Blizzard Developer APIs,
                        and is subject to the{" "}
                        <Link
                            href="https://www.blizzard.com/en-us/legal/a2989b50-5f16-43b1-abec-2ae17cc09dd6/blizzard-developer-api-terms-of-use"
                            target="_blank"
                            rel="noopener noreferrer noindex nofollow"
                        >
                            Blizzard Developer API Terms of Use
                        </Link>
                        .
                    </p>

                    <p>
                        Use of this data is subject to our <Link href={route('privacypolicy')}>privacy policy</Link> and that of{" "}
                        <Link 
                            href="https://www.blizzard.com/en-us/legal/a4380ee5-5c8d-4e3b-83b7-ea26d01a9918/blizzard-entertainment-online-privacy-policy"
                            target="_blank"
                            rel="noopener noreferrer noindex nofollow"
                        >
                            Blizzard Entertainment, Inc.
                        </a>
                        . You have the right to decide how Blizzard processes your personal data and object to providing
                        us information through the API.
                    </p>

                    <p>If you have any questions about this statement please do not hesitate to contact an officer.</p>
                </main>
            </div>
        </Master>
    );
}
