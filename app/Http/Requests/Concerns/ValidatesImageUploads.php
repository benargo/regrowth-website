<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Support\Str;
use Spatie\MediaLibrary\HasMedia;

/**
 * For form requests that upload an image into one of a model's media
 * collections: validates the file against the types the collection accepts.
 */
trait ValidatesImageUploads
{
    /**
     * The rules for an image replacing the model's media collection of the same
     * name, accepting the types the collection accepts. Null removes the image.
     *
     * @return list<string>
     */
    protected function imageRules(HasMedia $model, string $collection): array
    {
        $mimeTypes = implode(',', $model->acceptedMimeTypes($collection));

        return ['sometimes', 'nullable', 'file', "mimetypes:{$mimeTypes}", 'max:2048'];
    }

    /**
     * The messages for an image field, naming the image it replaces and the
     * types it accepts.
     *
     * @return array<string, string>
     */
    protected function imageMessages(HasMedia $model, string $collection): array
    {
        $types = collect($model->acceptedMimeTypes($collection))
            ->map(fn (string $mimeType): string => Str::of($mimeType)->after('/')->before('+')->upper()->toString())
            ->join(', ', ' or ');

        $typeMessage = "The {$collection} must be a {$types} image.";

        return [
            "{$collection}.file" => $typeMessage,
            "{$collection}.mimetypes" => $typeMessage,
            "{$collection}.max" => "The {$collection} must be 2 MB or smaller.",
        ];
    }
}
