import Icon from "@/Components/FontAwesome/Icon";

export default function AutoSaved() {
    return (
        <span className="text-sm font-medium text-green-400">
            <Icon icon="check" style="solid" className="mr-2" />
            Saved
        </span>
    );
}
