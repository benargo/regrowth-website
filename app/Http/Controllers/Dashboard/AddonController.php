<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Models\LootCouncil\Item;
use App\Models\LootCouncil\ItemPriority;
use App\Models\LootCouncil\Priority;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;

class AddonController extends Controller
{
    public function export(Request $request)
    {
        return Inertia::render('Dashboard/Addon/Base64', [
            'exportedData' => $this->getBase64ExportedData($request),
        ]);
    }

    public function exportJson(Request $request)
    {
        return Inertia::render('Dashboard/Addon/JSON', [
            'exportedData' => $this->getJsonExportedData($request, JSON_PRETTY_PRINT),
        ]);
    }

    public function exportSchema()
    {
        return Inertia::render('Dashboard/Addon/Schema', [
            'schema' => $this->getSchema(),
        ]);
    }

    protected function getBase64ExportedData(Request $request): string
    {
        return base64_encode($this->getJsonExportedData($request));
    }

    protected function getJsonExportedData(Request $request, $style = null): string
    {
        return json_encode($this->getExportedData($request), $style);
    }

    protected function getSchema(): array
    {
        return [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            '$id' => config('app.url').'/regrowth-loot-tool-schema.json?v=1.0.0',
            'title' => 'Regrowth Loot Tool Export Schema',
            'description' => 'Schema for the Regrowth Loot Tool addon data export format.',
            'type' => 'object',
            'properties' => [
                'system' => [
                    'type' => 'object',
                    'properties' => [
                        'date_generated' => ['type' => 'string', 'format' => 'date-time'],
                        'user' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
                'priorities' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'id' => ['type' => 'integer'],
                            'name' => ['type' => 'string'],
                            'icon' => ['type' => ['string', 'null']],
                        ],
                    ],
                ],
                'items' => [
                    'type' => 'array',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'item_id' => ['type' => 'integer'],
                            'notes' => ['type' => 'string'],
                            'priorities' => [
                                'type' => 'array',
                                'items' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'priority_id' => ['type' => 'integer'],
                                        'weight' => ['type' => 'integer'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ];
    }

    protected function getExportedData(Request $request): array
    {
        $priorities = Priority::has('items')->get()->map(function (Priority $priority) {
            return [
                'id' => $priority->id,
                'name' => $priority->title,
                'icon' => $priority->media['media_name'] ?? null,
            ];
        });

        $items = Item::has('priorities')->select('id', 'notes')->get();

        return [
            'system' => [
                'date_generated' => Carbon::now()->toIso8601String(),
                'user' => [
                    'id' => $request->user()->id,
                    'name' => $request->user()->displayName,
                ],
            ],
            'priorities' => $priorities,
            'items' => $this->mapItemsWithPriorities($items),
        ];
    }

    protected function mapItemsWithPriorities(Collection $items): Collection
    {
        return $items->map(function (Item $item) {
            return [
                'item_id' => $item->id,
                'notes' => $this->cleanNotes($item->notes),
                'priorities' => ItemPriority::where('item_id', $item->id)
                    ->select('priority_id', 'weight')
                    ->get(),
            ];
        });
    }

    protected function cleanNotes(?string $notes): string
    {
        if ($notes === null) {
            return '';
        }

        // Remove custom wowhead links: !wh[text](item=12345) -> text
        $notes = preg_replace('/!wh\[([^\]]*)\]\([^)]+\)/', '$1', $notes);

        // Remove standard markdown links: [text](url) -> text
        $notes = preg_replace('/\[([^\]]*)\]\([^)]+\)/', '$1', $notes);

        // Remove bold/italic: **text**, *text*
        $notes = preg_replace('/(\*\*)(.*?)\1/', '$2', $notes);
        $notes = preg_replace('/(\*|_)(.*?)\1/', '$2', $notes);

        // Remove underline: __text__
        $notes = preg_replace('/(__)(.*?)\1/', '$2', $notes);

        // Remove inline code: `code`
        $notes = preg_replace('/`([^`]*)`/', '$1', $notes);

        // Remove headers: # Header
        $notes = preg_replace('/^#{1,6}\s*/m', '', $notes);

        // Remove strikethrough: ~~text~~
        $notes = preg_replace('/~~(.*?)~~/', '$1', $notes);

        // Normalize whitespace
        return trim(preg_replace('/\s+/', ' ', $notes));
    }
}
