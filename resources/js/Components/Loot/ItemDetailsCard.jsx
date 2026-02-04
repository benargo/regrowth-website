import { Link } from "@inertiajs/react";

export default function ItemDetailsCard({ item }) {
    return (
        <div className="flex flex-row items-start gap-2 md:gap-6">
            <div className="h-8 w-8 flex-none md:h-24 md:w-24">
                <Link
                    href={item.wowhead_url}
                    data-wowhead={`item=${item.id}&domain=tbc`}
                    target="_blank"
                    rel="noopener noreferrer"
                >
                    <img src={item.icon} alt={item.name} className="box-shadow h-8 w-8 rounded-lg md:h-24 md:w-24" />
                </Link>
            </div>
            <div className="flex w-full flex-initial flex-col">
                <h2 className={`text-2xl font-bold text-quality-${item.quality?.name?.toLowerCase() || "common"} mb-2`}>
                    {item.name}
                </h2>
                <div className="mb-4 flex flex-col gap-2 md:flex-row">
                    {/* Item Details */}
                    <div className="flex-auto">
                        {item.id && (
                            <p className="mb-2">
                                <strong>Item ID:</strong> {item.id}
                            </p>
                        )}
                        {item.item_class && (
                            <p className="mb-2">
                                <strong>Type:</strong> {item.item_class}
                                {item.item_subclass ? ` / ${item.item_subclass}` : ""}
                            </p>
                        )}
                        {item.inventory_type && (
                            <p className="mb-2">
                                <strong>Slot:</strong> {item.inventory_type}
                            </p>
                        )}
                        {item.boss && (
                            <p className="mb-2">
                                <strong>Drops from:</strong> {item.boss.name}
                            </p>
                        )}
                        {item.group && (
                            <p className="mb-2">
                                <strong>Group:</strong> {item.group}
                            </p>
                        )}
                    </div>
                    {/* Wowhead Link */}
                    <div className="flex-auto md:text-right">
                        <Link
                            href={item.wowhead_url}
                            data-wowhead={`item=${item.id}&domain=tbc`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-block rounded-md bg-wowhead px-4 py-2 font-medium text-white transition-opacity hover:opacity-90"
                        >
                            <img
                                src="/images/logo_wowhead_white.webp"
                                alt="Wowhead Logo"
                                className="-mt-1 mr-2 inline-block h-5 w-5"
                            />
                            View on Wowhead
                        </Link>
                    </div>
                </div>
            </div>
        </div>
    );
}
