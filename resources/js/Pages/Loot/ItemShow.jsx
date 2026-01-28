import Master from '@/Layouts/Master';
import { useState } from 'react';
import { router, Link } from '@inertiajs/react';

function PriorityItem({ priority }) {
    return (
        <div className="min-w-50 flex items-center justify-center gap-2 p-6 border border-primary rounded-md bg-brown-800">
            {priority.media && (
                <img src={priority.media} alt="" className="w-6 h-6 rounded-sm" />
            )}
            <span>{priority.title}</span>
        </div>
    );
}

function PriorityDisplay({ priorities }) {
    if (!priorities || priorities.length === 0) {
        return <p className="text-gray-500 italic">Item not subject to loot council.</p>;
    }

    // Sort by weight (ascending) and group by weight
    const sorted = [...priorities].sort((a, b) => a.weight - b.weight);
    const grouped = sorted.reduce((acc, priority) => {
        const weight = priority.weight;
        if (!acc[weight]) {
            acc[weight] = [];
        }
        acc[weight].push(priority);
        return acc;
    }, {});

    // Build display: join same-weight with " = ", different weights with " > "
    const weights = Object.keys(grouped).sort((a, b) => a - b);

    return (
        <span className="text-lg flex-col items-center flex-wrap gap-2">
            {weights.map((weight, weightIndex) => (
                <div key={weight} className="my-4">
                    {weightIndex > 0 && (
                        <div className="font-bold text-4xl text-center text-amber-600 ml-12 my-4 mx-1"><i className="fas fa-chevron-down"></i></div>
                    )}
                    <div className="flex items-center justify-center">
                        <div className="w-12 flex-none text-4xl">{weightIndex+1}</div>
                        <div className="w-full flex items-center justify-center">
                            {grouped[weight].map((priority, index) => (
                                <div key={`priority-${priority.id}`} className="w-92 flex items-center justify-center my-4">
                                    {index > 0 && (
                                        <div key={`separator-${index}`} className="w-12 flex-none items-center text-center font-bold text-2xl text-amber-600">
                                            <i className="fas fa-equals"></i>
                                        </div>
                                    )}
                                    <PriorityItem priority={priority} />
                                </div>
                            ))}
                        </div>
                    </div>
                </div>
            ))}
        </span>
    );
}

export default function ItemShow({ item, can_edit }) {
    console.log(item);
    return (
        <Master title={item.data.name}>
            {/* Header */}
            <div className="bg-karazhan py-24 text-white">
                <div className="container mx-auto px-4">
                    <h1 className="text-4xl font-bold text-center">
                        Loot Priorities
                    </h1>
                    {/* Insert search bar here in the future */}
                </div>
            </div>
            {/* Tool navigation */}
            <nav className="bg-brown-900 shadow">
                <div className="container mx-auto px-4">
                    <div className="flex items-center justify-between h-12">
                        <div className="flex flex-1 space-x-4">
                            <Link
                                href={route('loot.index', { raid_id: item.data.raid.id })}
                                className="text-white hover:bg-brown-800 px-3 py-2 rounded-md text-sm font-medium"
                            >
                                <i className="fas fa-arrow-left mr-2"></i>
                                Back to {item.data.raid.name} loot
                            </Link>
                        </div>
                        <div className="flex items-center space-x-4">
                            {can_edit && (
                                <Link
                                    href={route('loot.items.edit', { item: item.data.id })}
                                    className="text-white hover:bg-brown-800 px-3 py-2 rounded-md text-sm font-medium"
                                >
                                    <i className="fas fa-edit mr-2"></i>
                                    Edit Priorities
                                </Link>
                            )}
                        </div>
                    </div>
                </div>
            </nav>
            {/* Content */}
            <main className="container mx-auto px-4 py-8">
                <div className="flex flex-row items-start space-x-8">
                    <div className="flex-none w-24 h-24 mb-8">
                        <Link href={item.data.wowhead_url} data-wowhead={`item=${item.data.id}&domain=tbc`} target="_blank" rel="noopener noreferrer">
                            <img
                                src={item.data.icon}
                                alt={item.data.name}
                                className="w-24 h-24 rounded-lg box-shadow"
                            />
                        </Link>
                    </div>
                    <div className="w-64 flex-auto">
                        {/* Item Details */}
                        <h2 className={`text-2xl font-bold mb-4 text-quality-${item.data.quality?.name?.toLowerCase() || 'common'}`}>{item.data.name}</h2>
                        {item.data.item_class && <p className="mb-2"><strong>Type:</strong> {item.data.item_class}{item.data.item_subclass ? ` / ${item.data.item_subclass}` : ''}</p>}
                        {item.data.inventory_type && <p className="mb-2"><strong>Slot:</strong> {item.data.inventory_type}</p>}
                        {item.data.boss && <p className="mb-2"><strong>Drops from:</strong> {item.data.boss.name}</p>}
                    </div>
                    {/* Wowhead Link */}
                    <div className="w-32 flex-auto text-right">
                        <Link
                            href={item.data.wowhead_url}
                            data-wowhead={`item=${item.data.id}&domain=tbc`}
                            target="_blank"
                            rel="noopener noreferrer"
                            className="inline-block bg-wowhead text-white px-4 py-2 rounded-md font-medium hover:opacity-90 transition-opacity"
                        >
                            <img src="/images/logo_wowhead_white.webp" alt="Wowhead Logo" className="inline-block w-5 h-5 mr-2 -mt-1" />
                            View on Wowhead
                        </Link>
                    </div>
                </div>
                <h2 className="text-xl font-bold mt-8 mb-4">Loot Priorities</h2>
                {/* Priorities List */}
                {item.data.priorities.length > 0 ? (
                    <div className="mt-8 w-full">
                        <PriorityDisplay priorities={item.data.priorities} />
                    </div>
                ) : (
                    <p className="text-gray-300">No loot priorities have been set for this item.</p>
                )}
            </main>
        </Master>
    );
}