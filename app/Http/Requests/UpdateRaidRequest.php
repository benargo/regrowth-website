<?php

namespace App\Http\Requests;

use App\Enums\BannerPosition;
use App\Http\Requests\Concerns\ChecksEditLock;
use App\Models\Phase;
use App\Models\Raid;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UpdateRaidRequest extends StoreRaidRequest
{
    use ChecksEditLock;

    /**
     * Get the validation rules that apply to the request. Every key is
     * optional, and only the keys sent are validated.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            ...collect($this->detailsRules())->map(fn (array $rules): array => ['sometimes', ...$rules])->all(),
            'banner' => ['sometimes', 'nullable', 'file', 'mimetypes:'.implode(',', $this->bannerMimeTypes()), 'max:2048'],
            'banner_position' => ['sometimes', 'nullable', Rule::enum(BannerPosition::class)],
            'boss_ids' => ['sometimes', 'array'],
            'boss_ids.*' => ['integer'],
        ];
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        $types = collect($this->bannerMimeTypes())
            ->map(fn (string $mimeType): string => Str::of($mimeType)->after('/')->upper()->toString())
            ->join(', ', ' or ');

        return [
            ...parent::messages(),
            'banner.file' => "The banner must be a {$types} image.",
            'banner.mimetypes' => "The banner must be a {$types} image.",
            'banner.max' => 'The banner must be 2 MB or smaller.',
        ];
    }

    /**
     * Get the human-readable field names used in default messages.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            ...parent::attributes(),
            'banner_position' => 'banner position',
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            $this->editLockCheck('raid', 'raid'),
            ...parent::after(),
            $this->phaseStaysInGameVersionCheck(),
            $this->bannerPositionNeedsBannerCheck(),
            $this->ownBossesCheck(),
        ];
    }

    /**
     * The MIME types the raid's banner collection accepts.
     *
     * @return list<string>
     */
    private function bannerMimeTypes(): array
    {
        return $this->route('raid')->acceptedMimeTypes(Raid::MEDIA_BANNER);
    }

    /**
     * An after() hook that keeps a raid with bosses, events or items within
     * its game version when its phase changes.
     *
     * @return Closure(Validator): void
     */
    private function phaseStaysInGameVersionCheck(): Closure
    {
        return function (Validator $validator): void {
            if (! $this->has('phase_id') || $validator->errors()->has('phase_id')) {
                return;
            }

            $raid = $this->route('raid');
            $targetPhase = Phase::find($this->input('phase_id'));
            $currentGameVersion = $raid->owningGameVersion();

            if ($targetPhase->game_version_id === $currentGameVersion?->id || ! $this->hasLinkedRecords($raid)) {
                return;
            }

            $scope = $currentGameVersion === null ? 'with no game version' : "in {$currentGameVersion->title}";

            $validator->errors()->add('phase_id', "This raid already has bosses, events or items, so it can only move to a phase {$scope}.");
        };
    }

    /**
     * Whether any boss, event or item is linked to the raid.
     */
    private function hasLinkedRecords(Raid $raid): bool
    {
        return $raid->bosses()->exists() || $raid->events()->exists() || $raid->items()->exists();
    }

    /**
     * An after() hook that refuses a banner position unless the raid keeps a
     * banner: a stored one not removed by this request, or one uploaded with it.
     *
     * @return Closure(Validator): void
     */
    private function bannerPositionNeedsBannerCheck(): Closure
    {
        return function (Validator $validator): void {
            if (! $this->filled('banner_position') || $validator->errors()->hasAny(['banner', 'banner_position'])) {
                return;
            }

            $isUploading = $this->hasFile('banner');
            $isRemoving = $this->has('banner') && ! $isUploading;
            $keepsBanner = $isUploading || (! $isRemoving && $this->route('raid')->hasMedia(Raid::MEDIA_BANNER));

            if (! $keepsBanner) {
                $validator->errors()->add('banner_position', 'Upload a banner before choosing its position.');
            }
        };
    }

    /**
     * An after() hook that accepts a boss order only when it lists exactly
     * the raid's own bosses, each once.
     *
     * @return Closure(Validator): void
     */
    private function ownBossesCheck(): Closure
    {
        return function (Validator $validator): void {
            if (! $this->has('boss_ids') || $validator->errors()->hasAny(['boss_ids', 'boss_ids.*'])) {
                return;
            }

            $given = collect($this->input('boss_ids'))->map(fn (mixed $id): int => (int) $id)->sort()->values()->all();
            $own = $this->route('raid')->bosses()->pluck('id')->sort()->values()->all();

            if ($given !== $own) {
                $validator->errors()->add('boss_ids', "Reorder this raid's own bosses.");
            }
        };
    }
}
