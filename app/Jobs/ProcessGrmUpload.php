<?php

namespace App\Jobs;

use App\Events\GrmUploadProcessed;
use App\Exceptions\CharacterTooLowLevelException;
use App\Models\Character;
use App\Models\GuildRank;
use App\Notifications\GrmUploadCompleted;
use App\Notifications\GrmUploadFailed;
use App\Services\Blizzard\BlizzardService;
use App\Services\Blizzard\Exceptions\CharacterNotFoundException;
use App\Services\Discord\Discord;
use App\Services\Discord\Notifications\NotifiableChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Queue\Middleware\Skip;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ProcessGrmUpload implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     */
    public int $backoff = 60;

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 10800; // 3 hours

    /**
     * Create a new job instance.
     *
     * @param  array{delimiter: string, headers: array<int, string>, rows: array<int, array<string, string>>}  $grmData
     */
    public function __construct(
        public array $grmData,
    ) {}

    public function middleware(): array
    {
        return [
            Skip::when(empty($this->grmData['rows'])),
        ];
    }

    /**
     * The cache key used to track upload progress.
     */
    public const PROGRESS_CACHE_KEY = 'grm_upload:progress';

    /**
     * The number of hours the progress cache entry lives.
     */
    public const PROGRESS_CACHE_TTL_HOURS = 4;

    /**
     * Execute the job.
     *
     * Flow:
     *   1. Mark progress as "processing" in cache so the UI can poll for status.
     *   2. Iterate every CSV row, delegating each to processRow(). Errors are
     *      bucketed into skipped (level too low), warnings (character not found
     *      in the Blizzard API), and hard errors (unexpected failures). Model
     *      events are suppressed for the entire loop to avoid N+1 side-effects.
     *   3. Persist the final counters back to cache, send a Discord notification
     *      to the officer channel, and — when at least one row succeeded — fire
     *      the GrmUploadProcessed event for any downstream listeners.
     */
    public function handle(BlizzardService $blizzard, Discord $discord): void
    {
        // --- Step 1: initialise progress cache ---
        Cache::put(self::PROGRESS_CACHE_KEY, [
            'status' => 'processing',
            'step' => 1,
            'total' => 3,
            'message' => 'Processing GRM roster data...',
            'processedCount' => 0,
            'skippedCount' => 0,
            'warningCount' => 0,
            'errorCount' => 0,
            'errors' => [],
        ], now()->addHours(self::PROGRESS_CACHE_TTL_HOURS));

        $delimiter = $this->grmData['delimiter'];
        // GRM exports use one delimiter for columns and the opposite for alt lists.
        $altDelimiter = $delimiter === ',' ? ';' : ',';
        $rows = $this->grmData['rows'];

        $processedCount = 0;
        $errorCount = 0;
        $errors = [];
        $warningCount = 0;
        $skippedCount = 0;

        // --- Step 2: process each row, suppressing model events to prevent side-effects ---
        Character::withoutEvents(function () use (
            $rows,
            $altDelimiter,
            $blizzard,
            &$processedCount,
            &$errorCount,
            &$errors,
            &$warningCount,
            &$skippedCount,
        ) {
            foreach ($rows as $row) {
                try {
                    $this->processRow($row, $altDelimiter, $blizzard);
                    $processedCount++;
                } catch (CharacterTooLowLevelException $e) {
                    // Below level 60 — skip silently, not an error.
                    $skippedCount++;
                    $characterName = $row['Name'] ?? 'Unknown';
                    Log::notice("GRM Upload: Character too low level {$characterName}", [
                        'error' => $e->getMessage(),
                        'row' => $row,
                    ]);
                } catch (CharacterNotFoundException $e) {
                    // Blizzard API returned no match — warn but continue.
                    $warningCount++;
                    $characterName = $row['Name'] ?? 'Unknown';
                    Log::warning("GRM Upload: Character not found via Blizzard API for {$characterName}", [
                        'error' => $e->getMessage(),
                        'row' => $row,
                    ]);
                } catch (\Exception $e) {
                    // Unexpected failure — record for the summary notification.
                    $errorCount++;
                    $characterName = $row['Name'] ?? 'Unknown';
                    $errors[] = "{$characterName}: {$e->getMessage()}";
                    Log::warning("GRM Upload: Failed to process character {$characterName}", [
                        'error' => $e->getMessage(),
                        'row' => $row,
                    ]);
                }
            }
        });

        Log::info('GRM Upload completed', [
            'processed' => $processedCount,
            'errors' => $errorCount,
            'skipped' => $skippedCount,
            'total' => count($rows),
        ]);

        // --- Step 3: finalise cache, notify Discord, dispatch event ---
        $channel = NotifiableChannel::fromConfig('officer', $discord);

        if ($errorCount > 0) {
            Cache::put(self::PROGRESS_CACHE_KEY, [
                'status' => 'failed',
                'step' => 3,
                'total' => 3,
                'message' => 'Upload completed with errors.',
                'processedCount' => $processedCount,
                'skippedCount' => $skippedCount,
                'warningCount' => $warningCount,
                'errorCount' => $errorCount,
                'errors' => $errors,
            ], now()->addHours(self::PROGRESS_CACHE_TTL_HOURS));

            $channel->notify(new GrmUploadFailed($processedCount, $errorCount, $errors));
        } else {
            Cache::put(self::PROGRESS_CACHE_KEY, [
                'status' => 'completed',
                'step' => 3,
                'total' => 3,
                'message' => 'Upload complete!',
                'processedCount' => $processedCount,
                'skippedCount' => $skippedCount,
                'warningCount' => $warningCount,
                'errorCount' => 0,
                'errors' => [],
            ], now()->addHours(self::PROGRESS_CACHE_TTL_HOURS));

            $channel->notify(new GrmUploadCompleted($processedCount, $skippedCount, $warningCount));
        }

        // Only dispatch the event when something was actually written; avoids
        // triggering downstream listeners (e.g. Discord embeds) on no-op runs.
        if ($processedCount > 0) {
            GrmUploadProcessed::dispatch($processedCount, $skippedCount, $warningCount, $errorCount, $errors);
        }
    }

    /**
     * Process a single CSV row.
     *
     * @param  array<string, string>  $row
     */
    protected function processRow(array $row, string $altDelimiter, BlizzardService $blizzard): void
    {
        $name = trim($row['Name']);
        $rankName = trim($row['Rank']);
        $level = trim($row['Level']);
        $lastOnline = trim($row['Last Online (Days)']);
        $mainAlt = trim($row['Main/Alt']);
        $playerAlts = trim($row['Player Alts'] ?? '');

        if (empty($name)) {
            return;
        }

        // Check character level
        $this->checkCharacterLevel($name, (int) $level);

        // Get character ID from Blizzard API
        try {
            $status = $blizzard->getCharacterStatus($name);
            $characterId = $status['id'];
        } catch (RequestException $e) {
            Log::error('GRM Upload: Could not fetch character data from Blizzard API.', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            throw new CharacterNotFoundException("Character {$name} not found via Blizzard API: ".$e->getMessage());
        }

        // Find or create the character
        $character = Character::query()->updateOrCreate(
            ['id' => $characterId],
            [
                'name' => $name,
                'is_main' => strtolower($mainAlt) === 'main',
            ]
        );

        // Update rank relationship
        $rank = GuildRank::query()->where('name', $rankName)->first();
        if ($rank) {
            $character->rank()->associate($rank);
            $character->save();
        }

        // Process alts if this is a main character
        if ($character->is_main && ! empty($playerAlts)) {
            $this->processAlts($character, $playerAlts, $altDelimiter, $blizzard);
        }
    }

    /**
     * Process alt characters and create links.
     */
    protected function processAlts(
        Character $mainCharacter,
        string $playerAlts,
        string $altDelimiter,
        BlizzardService $blizzard
    ): void {
        $altNames = explode($altDelimiter, $playerAlts);

        foreach ($altNames as $altName) {
            $altName = trim($altName);

            if (empty($altName)) {
                continue;
            }

            // Remove realm suffix (e.g., "-Thunderstrike" or "- Wild Growth")
            $altName = preg_replace('/\s*-\s*[\w\s]+$/', '', $altName);

            if (empty($altName)) {
                continue;
            }

            try {
                $altStatus = $blizzard->getCharacterProfile($altName);
                $altId = $altStatus['id'];
                $altLevel = (int) $altStatus['level'];

                $this->checkCharacterLevel($altName, $altLevel);

                // Find or create the alt character
                $altCharacter = Character::query()->updateOrCreate(
                    ['id' => $altId],
                    ['name' => $altName]
                );

                // Create the link if it doesn't exist
                // The linkedCharacters() relationship uses:
                // 'linked_character_id' for the current model (alt)
                // 'character_id' for the related model (main)
                if (! $altCharacter->linkedCharacters()
                    ->where('character_id', $mainCharacter->id)
                    ->exists()) {
                    $altCharacter->linkedCharacters()->attach($mainCharacter->id);
                }
            } catch (CharacterTooLowLevelException $e) {
                Log::notice('GRM Upload: Alt character too low level', [
                    'main' => $mainCharacter->name,
                    'alt' => $altName,
                    'error' => $e->getMessage(),
                ]);

                continue;
            } catch (RequestException $e) {
                Log::warning('GRM Upload: Could not process alt character', [
                    'main' => $mainCharacter->name,
                    'alt' => $altName,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }
        }
    }

    /**
     * Check if character meets level requirement.
     *
     * @throws CharacterTooLowLevelException
     */
    protected function checkCharacterLevel(string $name, int $level, int $minLevel = 60): void
    {
        if ($level < $minLevel) {
            throw new CharacterTooLowLevelException("Character {$name} is below the minimum required level of {$minLevel}.");
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error('GRM Upload job failed', [
            'error' => $exception->getMessage(),
            'trace' => $exception->getTraceAsString(),
        ]);

        Cache::put(self::PROGRESS_CACHE_KEY, [
            'status' => 'failed',
            'step' => 1,
            'total' => 3,
            'message' => 'Processing failed: '.$exception->getMessage(),
            'processedCount' => 0,
            'skippedCount' => 0,
            'warningCount' => 0,
            'errorCount' => 1,
            'errors' => [$exception->getMessage()],
        ], now()->addHours(self::PROGRESS_CACHE_TTL_HOURS));

        try {
            NotifiableChannel::fromConfig('officer', app(Discord::class))->notifyNow(
                new GrmUploadFailed(0, 1, [], $exception->getMessage())
            );
        } catch (\Exception $e) {
            Log::error('GRM Upload: Failed to send failure notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Get the tags that should be assigned to the job.
     *
     * @return array<int, string>
     */
    public function tags(): array
    {
        return ['grm-upload'];
    }
}
