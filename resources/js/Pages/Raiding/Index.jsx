import Master from "@/Layouts/Master";
import SharedHeader from "@/Components/SharedHeader";
import Icon from "@/Components/FontAwesome/Icon";

export default function Index() {
    return (
        <Master title="Raiding with Regrowth">
            <SharedHeader title="Raiding with Regrowth" backgroundClass="bg-illidan" />
            <div className="py-12 text-white">
                <div className="container mx-auto px-4">{/* TODO: Add content here */}</div>
            </div>
        </Master>
    );
}
