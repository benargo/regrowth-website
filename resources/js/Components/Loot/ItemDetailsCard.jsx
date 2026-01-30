import { Link } from '@inertiajs/react';

export default function ItemDetailsCard({ item }) {
    return (
        <div className="flex flex-row items-start gap-2 md:gap-6">
            <div className="flex-none w-8 md:w-24 h-8 md:h-24">
                <Link href={item.wowhead_url} data-wowhead={`item=${item.id}&domain=tbc`} target="_blank" rel="noopener noreferrer">
                    <img
                        src={item.icon}
                        alt={item.name}
                        className="w-8 md:w-24 h-8 md:h-24 rounded-lg box-shadow"
                    />
                </Link>
            </div>
            <div className="w-full flex flex-col flex-initial">
                <h2 className={`text-2xl font-bold text-quality-${item.quality?.name?.toLowerCase() || 'common'} mb-2`}>{item.name}</h2>
                <div className="flex flex-col md:flex-row gap-2 mb-4">
                    {/* Item Details */}
                    <div className="flex-auto">
                        {item.id && <p className="mb-2"><strong>Item ID:</strong> {item.id}</p>}
                        {item.item_class && <p className="mb-2"><strong>Type:</strong> {item.item_class}{item.item_subclass ? ` / ${item.item_subclass}` : ''}</p>}
                        {item.inventory_type && <p className="mb-2"><strong>Slot:</strong> {item.inventory_type}</p>}
                        {item.boss && <p className="mb-2"><strong>Drops from:</strong> {item.boss.name}</p>}
                        {item.group && <p className="mb-2"><strong>Group:</strong> {item.group}</p>}
                    </div>
                    {/* Wowhead Link */}
                    <div className="flex-auto md:text-right">
                        <Link
                            href={item.wowhead_url}
                            data-wowhead={`item=${item.id}&domain=tbc`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-block bg-wowhead text-white px-4 py-2 rounded-md font-medium hover:opacity-90 transition-opacity"
                        >
                            <img src="/images/logo_wowhead_white.webp" alt="Wowhead Logo" className="inline-block w-5 h-5 mr-2 -mt-1" />
                            View on Wowhead
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
