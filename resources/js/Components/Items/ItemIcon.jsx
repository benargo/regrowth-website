export default function ItemIcon({ itemId, itemName, iconUrl, size, itemQuality }) {
    return (
        <a
            href={`https://www.wowhead.com/tbc/item=${itemId}`}
            data-wowhead={`item=${itemId}&domain=tbc`}
            target="_blank"
            rel="noopener noreferrer"
            tabIndex={-1}
        >
            <img
                src={iconUrl}
                alt={itemName}
                className={`h-${size} w-${size} rounded border-2 ${itemQuality ?? "border-quality-common"}`}
            />
        </a>
    );
}
