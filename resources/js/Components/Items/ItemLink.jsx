import ItemIcon from "@/Components/Items/ItemIcon";

/**
 * An inline Wowhead item link: a small icon followed by the item's name,
 * coloured by its quality, both showing the Wowhead tooltip on hover.
 */
export default function ItemLink({ id, name, icon, quality = "common", domain = "tbc" }) {
    return (
        <span className="inline-flex items-baseline gap-1 align-baseline whitespace-nowrap">
            <span className="self-center">
                <ItemIcon
                    itemId={id}
                    itemName={name}
                    iconUrl={`https://wow.zamimg.com/images/wow/icons/small/${icon}.jpg`}
                    itemQuality={`border-quality-${quality}`}
                    size={5}
                    domain={domain}
                />
            </span>
            <a
                href={`https://www.wowhead.com/${domain}/item=${id}`}
                data-wowhead={`item=${id}&domain=${domain}`}
                target="_blank"
                rel="noopener noreferrer"
                className={`text-quality-${quality} font-semibold`}
            >
                {name}
            </a>
        </span>
    );
}
