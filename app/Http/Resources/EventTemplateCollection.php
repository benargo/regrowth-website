<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Arr;

class EventTemplateCollection extends ResourceCollection
{
    /**
     * Disable the data wrapper since we are performing this manually.
     *
     * @var string|null
     */
    public static $wrap = null;

    /**
     * Transform the resource collection into an array.
     *
     * @return array<int|string, mixed>
     */
    public function toArray(Request $request): array
    {
        $templates = $this->collection->map(fn (Event $template) => [
            'id' => $template->id,
            'title' => $template->title,
            'updated_at' => $template->updated_at->toIso8601String(),
            'raids' => RaidResource::collection($template->raids()->get())->resolve($request),
        ])->values()->all();

        $raidGroups = $this->buildRaidGroups($templates);

        return [
            'templates' => $templates,
            'raidGroups' => $raidGroups,
        ];
    }

    /**
     * Build a grouped structure where each raid contains its associated templates.
     * Multi-raid templates appear under every raid they belong to.
     *
     * @param  array<int, mixed>  $templates
     * @return array<int, mixed>
     */
    private function buildRaidGroups(array $templates): array
    {
        $byRaid = [];

        foreach ($templates as $template) {
            foreach (Arr::get($template, 'raids', []) as $raid) {
                $raidId = Arr::get($raid, 'id');

                if (! isset($byRaid[$raidId])) {
                    $byRaid[$raidId] = [
                        'raid' => $raid,
                        'templates' => [],
                    ];
                }

                $byRaid[$raidId]['templates'][] = $template;
            }
        }

        return array_values($byRaid);
    }
}
