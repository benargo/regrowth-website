<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateBossStrategyRequest;
use App\Http\Resources\PhaseResource;
use App\Http\Resources\RaidBossesCollection;
use App\Models\Boss;
use App\Models\Phase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Attributes\Controllers\Authorize;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

class BossStrategyController extends Controller
{
    /**
     * Show a list of all bosses to navigate to individual strategy pages.
     */
    public function index(Request $request)
    {
        $phases = Phase::with(['raids'])->orderBy('number')->get();

        $route = Route::currentRouteName();

        $view = match ($route) {
            'raiding.boss-strategies.index' => 'Raiding/BossStrategies/Index',
            'management.boss-strategies.index' => 'Manage/BossStrategies/Index',
            default => throw new \Exception("Unexpected route name: $route"),
        };

        return Inertia::render($view, [
            'bosses' => new RaidBossesCollection(Boss::orderBy('raid_id')->orderBy('encounter_order')->get()),
            'phases' => PhaseResource::collection($phases)->resolve($request),
        ]);
    }

    /**
     * Show a boss's strategy.
     */
    public function show(Boss $boss, string $slug)
    {
        return Inertia::render('Raiding/BossStrategies/Show', [
            'boss' => $boss->load('raid')->toResource(),
        ]);
    }

    /**
     * Show the form to edit a boss strategy, including the current strategy content.
     */
    #[Authorize('update', 'boss')]
    public function edit(Request $request, Boss $boss, string $slug)
    {
        return Inertia::render('Manage/BossStrategies/Edit', [
            'boss' => $boss->load('raid')->toResource(),
        ]);
    }

    /**
     * Handle the submission of an updated boss strategy, validating the input and saving it to the database.
     */
    #[Authorize('update', 'boss')]
    public function update(UpdateBossStrategyRequest $request, Boss $boss): RedirectResponse
    {
        $notes = $request->input('notes');
        if ($notes) {
            $boss->update(['notes' => $notes]);
        } elseif ($boss->notes !== null) {
            $boss->update(['notes' => null]);
        }

        // Delete specified images by matching media names or URLs
        $deletedImages = $request->input('deleted_images', []);
        if (! empty($deletedImages)) {
            foreach ($boss->getMedia() as $media) {
                $mediaUrl = $media->getUrl();
                // Match by full URL or by checking if URL ends with the deleted image path
                foreach ($deletedImages as $deletedUrl) {
                    if ($mediaUrl === $deletedUrl || str_ends_with($mediaUrl, $deletedUrl)) {
                        $media->delete();
                        break;
                    }
                }
            }
        }

        // Add new images
        $newImages = $request->file('images', []);
        foreach ($newImages as $file) {
            $boss->addMedia($file)->toMediaCollection();
        }

        // Reorder images
        $imageOrder = $request->input('image_order', []);
        if ($imageOrder) {
            $boss->refresh();
            $orderedIds = [];
            foreach ($imageOrder as $url) {
                $media = $boss->getMedia()->first(fn (Media $m) => $m->getUrl() === $url);
                if ($media) {
                    $orderedIds[] = $media->id;
                }
            }
            if ($orderedIds) {
                Media::setNewOrder($orderedIds);
            }
        }

        // Touch so the BroadcastsEvents trait fires the `updated` event even when
        // only image media (not notes) changed.
        $boss->touch();

        return back();
    }
}
