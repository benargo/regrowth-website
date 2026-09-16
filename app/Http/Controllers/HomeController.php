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
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

#[UsesTheme(Theme::Forever)]
class HomeController extends Controller
{
    /**
     * The number of upcoming events shown on the homepage.
     */
    private const UPCOMING_EVENT_LIMIT = 5;

    public function __construct(private readonly BlizzardConnector $blizzard) {}

    /**
     * Display the homepage.
     */
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
     * Playable races whose renders read visually oversized next to the rest
     * of the officer team at the same visible-character height — Gnome.
     */
    private const LARGE_RACE_IDS = [7];

    /**
     * Map each configured officer name to their full-body render.
     *
     * Deferred rather than synchronous: on a cold cache this is one Blizzard
     * round trip per officer, far too slow for a public landing page's first
     * paint. An officer with no character row, no attached render, or a failed
     * lookup maps to null and the card renders a silhouette instead.
     *
     * @return array<string, array{url: string, visibleTop: float, visibleBottom: float, isLargeRace: bool}|null>
     */
    private function resolveOfficerRenders(): array
    {
        return collect(config('guild.officers'))
            ->mapWithKeys(fn (array $officer): array => [
                $officer['name'] => $this->renderFor($officer['name']),
            ])
            ->all();
    }

    /**
     * Resolve one officer's render, dispatching a fetch when it is absent.
     *
     * Returns null on the first visit for a character whose render has not been
     * fetched yet; the dispatched job means the next visit has it. The visible
     * bounds default to the full frame when the render predates bounds being
     * measured, or measurement failed — the homepage falls back to sizing by
     * canvas height rather than breaking.
     *
     * @return array{url: string, visibleTop: float, visibleBottom: float, isLargeRace: bool}|null
     */
    private function renderFor(string $name): ?array
    {
        $character = Character::whereRaw('LOWER(name) = ?', [mb_strtolower($name)])->first();

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
                'isLargeRace' => in_array($character->playable_race_id, self::LARGE_RACE_IDS, true),
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
