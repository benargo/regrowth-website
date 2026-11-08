<?php

namespace App\Notifications;

use App\Contracts\HasCharacterMedia;
use App\Http\Integrations\Discord\Data\Messages\EmbedData;
use App\Http\Integrations\Discord\Data\Messages\EmbedMediaData;
use App\Http\Integrations\Discord\Data\Messages\MessagePayloadData;
use App\Http\Integrations\Discord\Enums\EmbedType;
use App\Models\RacePlacement;
use App\Notifications\Discord\DiscordNotification;
use LogicException;

class RacePlacementClaimed extends DiscordNotification
{
    public function __construct(RacePlacement $placement)
    {
        $this->withRelatedModels([$placement]);
    }

    /**
     * Get the payload to send to Discord for this notification.
     *
     * @throws LogicException when the placement has no medal
     */
    public function toMessage(): MessagePayloadData
    {
        /** @var RacePlacement $placement */
        $placement = $this->hydrateOrFail($this->relatedModel(RacePlacement::class));
        $placement->load('character.playableClass');

        $character = $placement->character;
        $medal = $placement->medal ?? throw new LogicException("Race placement {$placement->id} at position {$placement->position} has no medal to announce.");
        $className = $character->playableClass?->name;
        $renderUrl = $character->getFirstMediaUrl(HasCharacterMedia::MEDIA_COLLECTION_RENDER);

        return MessagePayloadData::from([
            'embeds' => [EmbedData::from([
                'type' => EmbedType::Rich,
                'title' => "{$medal->name}: {$character->name} is {$medal->ordinal()} to level 60!",
                'description' => $className !== null
                    ? "Congratulations to {$character->name}, the {$className}, for reaching level 60 in World of Warcraft: Forever."
                    : "Congratulations to {$character->name} for reaching level 60 in World of Warcraft: Forever.",
                'color' => $medal->color(),
                'url' => route('race.index'),
                'timestamp' => $placement->reached_at->toIso8601String(),
                'image' => $renderUrl !== '' ? EmbedMediaData::from(['url' => $renderUrl]) : null,
            ])],
        ]);
    }
}
