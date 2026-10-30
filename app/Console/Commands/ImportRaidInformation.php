<?php

namespace App\Console\Commands;

use App\Actions\RaidInformation\PutChapter;
use App\Actions\RaidInformation\WriteChapterOrder;
use App\Contracts\Actions\RaidInformation\StoresRaidInformation;
use App\Rules\ChapterContent;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Container\Attributes\Storage;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

#[Signature('raid-information:import {directory : A local folder holding chapters.json and one <slug>.md per listed slug}')]
#[Description('Import raid information chapters from a local folder')]
class ImportRaidInformation extends Command implements StoresRaidInformation
{
    /**
     * Validate every chapter in the folder and, when all pass, write them and the chapter order to the disk.
     */
    public function handle(#[Storage(self::DISK)] Filesystem $disk): int
    {
        if ($disk->exists(self::ORDER_FILE) || $this->holdsMarkdown($disk->files())) {
            $this->error('The raid-information disk already holds chapters. Nothing was imported.');

            return self::FAILURE;
        }

        $directory = rtrim((string) $this->argument('directory'), '/');
        $orderPath = "{$directory}/".self::ORDER_FILE;

        if (! File::exists($orderPath)) {
            $this->error('chapters.json was not found in '.$directory.'.');

            return self::FAILURE;
        }

        $slugs = json_decode(File::get($orderPath), true);

        if (! is_array($slugs) || ! array_is_list($slugs)) {
            $this->error('chapters.json must hold a list of slugs.');

            return self::FAILURE;
        }

        $errors = [];

        if (! in_array(self::INTRODUCTION, $slugs, true)) {
            $errors[] = 'chapters.json must include the introduction.';
        }

        $contents = [];

        foreach ($slugs as $slug) {
            if (! is_string($slug) || preg_match(self::SLUG_PATTERN, $slug) !== 1) {
                $errors[] = 'Invalid slug: '.json_encode($slug);

                continue;
            }

            $path = "{$directory}/{$slug}.md";

            if (! File::exists($path)) {
                $errors[] = "{$slug}: {$slug}.md was not found.";

                continue;
            }

            $content = Str::of(File::get($path))->replace(["\r\n", "\r"], "\n")->trim()->value();

            $validator = validator(
                ['content' => $content],
                ['content' => ['required', 'string', ChapterContent::forSlug($slug)]],
            );

            if ($validator->fails()) {
                foreach ($validator->errors()->all() as $message) {
                    $errors[] = "{$slug}: {$message}";
                }

                continue;
            }

            $contents[$slug] = $content;
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        Cache::lock(self::WRITE_LOCK, 10)->block(5, function () use ($contents, $slugs): void {
            foreach ($contents as $slug => $content) {
                PutChapter::run($slug, $content);
            }

            WriteChapterOrder::run($slugs);
        });

        $this->info('Imported '.count($contents).' chapters.');

        return self::SUCCESS;
    }

    /**
     * @param  array<int, string>  $files
     */
    private function holdsMarkdown(array $files): bool
    {
        return array_filter($files, fn (string $file): bool => str_ends_with($file, '.md')) !== [];
    }
}
