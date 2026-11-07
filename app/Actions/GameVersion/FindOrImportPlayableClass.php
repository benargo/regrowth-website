<?php

namespace App\Actions\GameVersion;

use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\BlizzardNamespace;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassMediaRequest;
use App\Http\Integrations\Blizzard\Requests\PlayableClass\GetPlayableClassRequest;
use App\Jobs\AttachBlizzardIconToModel;
use App\Models\PlayableClass;
use Lorisleiva\Actions\Concerns\AsAction;
use Saloon\Exceptions\Request\FatalRequestException;
use Throwable;

/**
 * Returns the playable class with the given ID, importing it and queueing its
 * icons from Blizzard when it does not exist locally. An existing class with no
 * icons has them queued. A failed icon lookup is reported and the class is still
 * returned.
 */
class FindOrImportPlayableClass
{
    use AsAction;

    public function __construct(
        protected BlizzardConnector $blizzardConnector,
    ) {}

    /**
     * @throws BlizzardRequestException|FatalRequestException
     */
    public function handle(int $playableClassId, ?BlizzardNamespace $namespace): PlayableClass
    {
        $playableClass = PlayableClass::find($playableClassId);

        if ($playableClass === null) {
            $dto = $this->blizzardConnector
                ->send(new GetPlayableClassRequest($playableClassId, $namespace))
                ->dto();

            $playableClass = PlayableClass::updateOrCreate(['id' => $dto->id], ['name' => $dto->name]);
        }

        if (! $playableClass->hasMedia('blizzard_icons')) {
            $this->queueIcons($playableClass, $namespace);
        }

        return $playableClass;
    }

    private function queueIcons(PlayableClass $playableClass, ?BlizzardNamespace $namespace): void
    {
        try {
            $media = $this->blizzardConnector
                ->send(new GetPlayableClassMediaRequest($playableClass->id, $namespace))
                ->dto();
        } catch (Throwable $exception) {
            report($exception);

            return;
        }

        foreach ($media->assets as $asset) {
            AttachBlizzardIconToModel::dispatch(PlayableClass::class, $playableClass->id, $asset->value)
                ->delay(now()->addMinutes(5));
        }
    }
}
