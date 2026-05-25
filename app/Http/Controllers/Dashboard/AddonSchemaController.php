<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Inertia\Inertia;
use Inertia\Response;

#[Authorize('view-officer-dashboard')]
class AddonSchemaController extends Controller
{
    public function __invoke(): Response
    {
        return Inertia::render('Manage/Addon/ExportSchema', [
            'schema' => [
                '$schema' => 'https://json-schema.org/draft/2020-12/schema',
                '$id' => config('app.url').'/regrowth-loot-tool-schema.json?v=1.2.0',
                'title' => 'Regrowth Loot Tool Export Schema',
                'description' => 'Schema for the Regrowth Loot Tool addon data export format.',
                'type' => 'object',
                'properties' => [
                    'system' => [
                        'type' => 'object',
                        'properties' => [
                            'date_generated' => ['type' => 'integer'],
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
                                'notes' => ['type' => ['string', 'null']],
                            ],
                        ],
                    ],
                    'players' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'string'],
                                'attendance' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'first_attendance' => ['type' => 'string', 'format' => 'date-time'],
                                        'attended' => ['type' => 'integer'],
                                        'total' => ['type' => 'integer'],
                                        'percentage' => ['type' => 'number'],
                                    ],
                                ],
                            ],
                        ],
                    ],
                    'councillors' => [
                        'type' => 'array',
                        'items' => [
                            'type' => 'object',
                            'properties' => [
                                'id' => ['type' => 'integer'],
                                'name' => ['type' => 'string'],
                                'rank' => ['type' => 'string'],
                            ],
                        ],
                    ],
                ],
            ],
        ]);
    }
}
