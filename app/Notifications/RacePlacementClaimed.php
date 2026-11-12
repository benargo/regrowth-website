<?php

namespace App\Notifications;

use App\Contracts\HasCharacterMedia;
use App\Http\Integrations\Discord\Data\Messages\EmbedData;
use App\Http\Integrations\Discord\Data\Messages\EmbedMediaData;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use App\Http\Integrations\Discord\Enums\EmbedType;
use App\Models\RacePlacement;
use App\Notifications\Discord\DiscordNotification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Arr;
use LogicException;

class RacePlacementClaimed extends DiscordNotification
{
    /**
     * @param  Collection<int, RacePlacement>  $placements  Placements sharing one position; never empty.
     */
    public function __construct(Collection $placements)
    {
        $this->withRelatedModels($placements);
    }

    /**
     * Get the payload to send to Discord for this notification, naming every holder of the position.
     *
     * @throws LogicException when the placement has no medal
     * @throws ModelNotFoundException when none of the placements can be resolved
     */
    public function toMessage(): MessagePayloadData
    {
        $ids = collect($this->mapRelatedModels())
            ->where('model_type', RacePlacement::class)
            ->pluck('model_id')
            ->all();

        $placements = RacePlacement::with('character.playableClass')->findMany($ids);

        if ($placements->isEmpty()) {
            throw (new ModelNotFoundException)->setModel(RacePlacement::class, $ids);
        }

        $placements = $placements->sortBy(fn (RacePlacement $placement) => $placement->character->full_name)->values();
        $first = $placements->first();
        $medal = $first->medal ?? throw new LogicException("Race placement {$first->id} at position {$first->position} has no medal to announce.");

        $names = $placements->map(fn (RacePlacement $placement) => $placement->character->full_name)->all();
        $phrases = $placements->map(function (RacePlacement $placement): string {
            $fullName = $placement->character->full_name;
            $className = $placement->character->playableClass?->name;

            return $className !== null ? "{$fullName}, the {$className}" : $fullName;
        });

        if ($placements->count() === 1) {
            $title = "{$medal->name}: {$names[0]} is {$medal->ordinal()} to level 60!";
            $description = "Congratulations to {$phrases[0]}".($first->character->playableClass !== null ? ',' : '').' for reaching level 60 in World of Warcraft: Forever.';
            $renderUrl = $first->character->getFirstMediaUrl(HasCharacterMedia::MEDIA_COLLECTION_RENDER);
            $image = $renderUrl !== '' ? EmbedMediaData::from(['url' => $renderUrl]) : null;
        } else {
            $lastHasClass = $placements->last()->character->playableClass !== null;
            $penultimateHasClass = $placements->get($placements->count() - 2)->character->playableClass !== null;
            $title = "{$medal->name}: ".Arr::join($names, ', ', ' and ')." share {$medal->ordinal()} to level 60!";
            $description = 'Congratulations to '.Arr::join($phrases->all(), ', ', $penultimateHasClass ? ', and ' : ' and ').($lastHasClass ? ',' : '').' for reaching level 60 together in World of Warcraft: Forever.';
            $image = null;
        }

        return MessagePayloadData::from([
            'embeds' => [EmbedData::from([
                'type' => EmbedType::Rich,
                'title' => $title,
                'description' => $description,
                'color' => $medal->color(),
                'url' => route('race.index'),
                'timestamp' => $first->reached_at->toIso8601String(),
                'image' => $image,
            ])],
        ]);
    }
}
