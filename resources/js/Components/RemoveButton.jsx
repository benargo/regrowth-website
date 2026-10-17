import IconButton from "@/Components/IconButton";

export default function RemoveButton({ onClick, label = "Remove" }) {
    return (
        <IconButton
            icon="times"
            label={label}
            onClick={onClick}
            className="absolute -top-2 -right-2 flex h-6 w-6 items-center justify-center rounded-full bg-red-600 text-xs text-white transition-colors hover:bg-red-700"
        />
    );
}
