<?php

namespace App\Http\Controllers;

use App\Attributes\UsesTheme;
use App\Contracts\HasCharacterMedia;
use App\Enums\Theme;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterMediaRequest;
use App\Http\Resources\EventResource;
use App\Jobs\AttachRenderToCharacter;
use App\Models\Character;
use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

#[UsesTheme(Theme::Forever)]
class HomeController extends Controller
{
    private const UPCOMING_EVENT_LIMIT = 5;

    /** Gnome — renders visually oversized next to other races at the same visible-character height. */
    private const SMALL_RACE_IDS = [7];

    public function __construct(private readonly BlizzardConnector $blizzard) {}

    public function __invoke(Request $request): Response
    {
        return Inertia::render('Home', [
            'foreverLaunchAt' => config('guild.forever_launch_at'),
            'discordInviteUrl' => config('guild.discord_invite_url'),
            'officers' => config('guild.officers'),
            'canViewPlans' => $request->user() !== null,
            'officerRenders' => Inertia::defer(fn () => $this->resolveOfficerRenders()),
            'upcomingEvents' => Inertia::defer(function () use ($request) {
                return Cache::tags('raiding', 'events')->remember(
                    'events:upcoming:public',
                    now()->addMinutes(10),
                    fn () => EventResource::collectionForVisitors(
                        Event::live()
                            ->with('raids')
                            ->where('end_time', '>=', now())
                            ->orderBy('start_time')
                            ->take(self::UPCOMING_EVENT_LIMIT)
                            ->get()
                    )->map->resolve($request)->all()
                );
            }),
        ]);
    }

    /**
     * @return array<string, array{url: string, visibleTop: float, visibleBottom: float, isLargeRace: bool}|null>
     */
    private function resolveOfficerRenders(): array
    {
        $officerNames = collect(config('guild.officers'))->pluck('name');

        $charactersByLowerName = Character::query()
            ->whereIn(DB::raw('LOWER(name)'), $officerNames->map(fn (string $name): string => mb_strtolower($name)))
            ->with('media')
            ->get()
            ->keyBy(fn (Character $character): string => mb_strtolower($character->name));

        return $officerNames
            ->mapWithKeys(fn (string $name): array => [
                $name => $this->renderFor($charactersByLowerName->get(mb_strtolower($name))),
            ])
            ->all();
    }

    /**
     * @return array{url: string, visibleTop: float, visibleBottom: float, isLargeRace: bool}|null
     */
    private function renderFor(?Character $character): ?array
    {
        if ($character === null) {
            return null;
        }

        if ($character->hasMedia(HasCharacterMedia::MEDIA_COLLECTION_RENDER)) {
            $media = $character->getFirstMedia(HasCharacterMedia::MEDIA_COLLECTION_RENDER);
            $url = $media->getUrl() ?: null;

            if ($url === null) {
                return null;
            }

            return [
                'url' => $url,
                'visibleTop' => (float) ($media->getCustomProperty('visible_top') ?? 0.0),
                'visibleBottom' => (float) ($media->getCustomProperty('visible_bottom') ?? 1.0),
                'isLargeRace' => in_array($character->playable_race_id, self::SMALL_RACE_IDS, true),
            ];
        }

        try {
            $assets = $this->blizzard->send(new GetCharacterMediaRequest(
                $this->blizzard->defaultRealmSlug(),
                $character->name,
            ))->dto()->assets;

            $render = collect($assets)->first(fn ($asset): bool => $asset->key === 'main-raw');

            if ($render !== null) {
                AttachRenderToCharacter::dispatch($character->id, $render->value);
            }
        } catch (Throwable) {
            // A Blizzard outage must never break the homepage render.
        }

        return null;
    }
}
