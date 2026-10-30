<?php

namespace App\Actions\RaidInformation;

use App\Contracts\Actions\RaidInformation\StoresRaidInformation;
use App\Data\RaidInformation\ChapterData;
use App\Rules\AvailableChapterHeading;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsAction;

/**
 * Creates a chapter whose slug is derived from its heading and appends it to the chapter order.
 */
class CreateChapter implements StoresRaidInformation
{
    use AsAction;

    public function __construct(
        #[Storage(self::DISK)] protected Filesystem $disk,
    ) {}

    /**
     * @throws ValidationException When the slug is empty, reserved, in use or in Recently deleted, or the content is invalid.
     */
    public function handle(string $content): ChapterData
    {
        return Cache::lock(self::WRITE_LOCK, 10)->block(5, function () use ($content): ChapterData {
            Validator::make(
                ['content' => $content],
                ['content' => [new AvailableChapterHeading($this->disk)]],
            )->validate();

            $slug = Str::slug(ParseChapterHeading::run($content)->title);

            PutChapter::run($slug, $content);

            WriteChapterOrder::run([...ReadChapterOrder::run(), $slug]);

            return FindChapter::run($slug) ?? throw new LogicException("Chapter [{$slug}] was not found after being created.");
        });
    }
}
