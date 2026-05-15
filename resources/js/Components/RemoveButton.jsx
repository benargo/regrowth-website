import Icon from "@/Components/FontAwesome/Icon";

export default function RemoveButton({ onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            className="absolute -right-2 -top-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-xs text-white transition-colors hover:bg-red-700"
        >
            <Icon icon="times" style="solid" />
        </button>
    );
}
