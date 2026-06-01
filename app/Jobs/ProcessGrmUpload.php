<?php

namespace App\Jobs;

use App\Events\Broadcasts\GrmUploadCompleted as GrmUploadCompletedBroadcast;
use App\Events\Broadcasts\GrmUploadFailed as GrmUploadFailedBroadcast;
use App\Events\Broadcasts\GrmUploadProgressed;
use App\Events\Broadcasts\GrmUploadStarted;
use App\Events\GrmUploadProcessed;
use App\Exceptions\CharacterTooLowLevelException;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\CharacterNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterStatusRequest;
use App\Models\Character;
use App\Models\GuildRank;
use App\Models\User;
use App\Notifications\GrmUploadCompleted;
use App\Notifications\GrmUploadFailed;
use App\Services\Blizzard\Exceptions\BlizzardApiException;
use App\Services\Discord\Discord;
use App\Services\Discord\Exceptions\RateLimitedException;
use App\Services\Discord\Notifications\NotifiableChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\Skip;
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
    public $timeout = 300; // 5 minutes

    /**
     * The timestamp of the last progress broadcast, used to throttle updates.
     */
    private ?float $lastBroadcastAt = null;

    /**
     * Create a new job instance.
     *
     * @param  array{delimiter: string, headers: array<int, string>, rows: array<int, array<string, string>>}  $grmData
     */
    public function __construct(
        public array $grmData,
        public string $userId,
    ) {}

    public function middleware(): array
    {
        return [
            Skip::when(empty($this->grmData['rows'])),
        ];
    }

    /**
     * Execute the job.
     *
     * Flow:
     *   1. Guard the uploading user, then broadcast GrmUploadStarted so the UI
     *      can flip from "queued" to "processing".
     *   2. Iterate every CSV row, delegating each to processRow(). Errors are
     *      bucketed into skipped (level too low), warnings (character not found
     *      in the Blizzard API), and hard errors (unexpected failures). After
     *      each row a throttled GrmUploadProgressed broadcast carries the live
     *      tallies. Model events are suppressed for the loop to avoid
     *      N+1 side-effects.
     *   3. Broadcast the final tally, send a Discord notification to the officer
     *      channel, broadcast GrmUploadCompleted, and — when at least one row
     *      succeeded — fire the GrmUploadProcessed event for downstream listeners.
     */
    public function handle(BlizzardConnector $blizzard, Discord $discord): void
    {
        // Guard against a stale/deleted uploader; fail the job loudly if gone.
        User::findOrFail($this->userId);

        $delimiter = $this->grmData['delimiter'];
        // GRM exports use one delimiter for columns and the opposite for alt lists.
        $altDelimiter = $delimiter === ',' ? ';' : ',';
        $rows = $this->grmData['rows'];
        $total = count($rows);

        GrmUploadStarted::dispatch($this->userId, $total);

        $processedCount = 0;
        $errorCount = 0;
        $errors = [];
        $warningCount = 0;
        $skippedCount = 0;

        // --- Step 2: process each row, suppressing model events and timestamp touches ---
        // withoutTouching prevents touchOwners() from recursing into the self-referential
        // linkedCharacters BelongsToMany at scale, which overflows the PHP call stack via
        // Onceable::hashFromTrace() → debug_backtrace() at extreme depth.
        Character::withoutTouching(function () use (
            $rows,
            $total,
            $altDelimiter,
            $blizzard,
            &$processedCount,
            &$errorCount,
            &$errors,
            &$warningCount,
            &$skippedCount,
        ) {
            Character::withoutEvents(function () use (
                $rows,
                $total,
                $altDelimiter,
                $blizzard,
                &$processedCount,
                &$errorCount,
                &$errors,
                &$warningCount,
                &$skippedCount,
            ) {
                foreach ($rows as $row) {
                    $characterName = $row['Name'] ?? 'Unknown';

                    try {
                        $this->processRow($row, $altDelimiter, $blizzard);
                        $processedCount++;
                    } catch (CharacterTooLowLevelException $e) {
                        // Below level 60 — skip silently, not an error.
                        $skippedCount++;
                        Log::debug("GRM Upload: Character too low level {$characterName}", [
                            'error' => $e->getMessage(),
                            'row' => $row,
                        ]);
                    } catch (CharacterNotFoundException $e) {
                        // Blizzard API returned no match — warn but continue.
                        $warningCount++;
                        Log::debug("GRM Upload: Character not found via Blizzard API for {$characterName}", [
                            'error' => $e->getMessage(),
                            'row' => $row,
                        ]);
                    } catch (\Exception $e) {
                        // Unexpected failure — record for the summary notification.
                        $errorCount++;
                        $errors[] = "{$characterName}: {$e->getMessage()}";
                        Log::debug("GRM Upload: Failed to process character {$characterName}", [
                            'error' => $e->getMessage(),
                            'row' => $row,
                        ]);
                    }

                    $this->broadcastProgress(
                        $processedCount,
                        $skippedCount,
                        $warningCount,
                        $errorCount,
                        $total,
                        $characterName,
                    );
                }
            });
        });

        // Force a final tick so the UI lands on the exact totals.
        $this->broadcastProgress($processedCount, $skippedCount, $warningCount, $errorCount, $total, '', force: true);

        Log::debug('GRM Upload completed', [
            'processed' => $processedCount,
            'errors' => $errorCount,
            'skipped' => $skippedCount,
            'total' => $total,
        ]);

        // --- Step 3: notify Discord, broadcast completion, dispatch event ---
        try {
            $channel = NotifiableChannel::fromConfig('officer', $discord);

            if ($errorCount > 0) {
                $channel->notify(new GrmUploadFailed($processedCount, $errorCount, $errors));
            } else {
                $channel->notify(new GrmUploadCompleted($processedCount, $skippedCount, $warningCount));
            }

            // Row-level errors do not fail the run — the bar stays green. Only the
            // counts are broadcast; the full $errors detail goes to Discord above
            // (Reverb caps messages at 10 KB, which a large error list can exceed).
            GrmUploadCompletedBroadcast::dispatch(
                $this->userId,
                $processedCount,
                $skippedCount,
                $warningCount,
                $errorCount,
            );

            // Only dispatch the event when something was actually written; avoids
            // triggering downstream listeners (e.g. Discord embeds) on no-op runs.
            if ($processedCount > 0) {
                GrmUploadProcessed::dispatch($processedCount, $skippedCount, $warningCount, $errorCount, $errors);
            }
        } catch (RateLimitedException $e) {
            $this->release($e->retryAfter);

            Log::debug('ProcessGrmUpload: Discord rate limited sending notification, releasing job.', [
                'endpoint' => $e->endpoint,
                'retry_after' => $e->retryAfter,
                'scope' => $e->scope,
            ]);

            return;
        }
    }

    /**
     * Broadcast a live progress tick to the uploading user.
     *
     * Throttled to ~4/sec so a large roster doesn't flood the WebSocket; the
     * frontend animates between ticks. Pass force: true for the final tick so
     * the UI lands on the exact totals regardless of timing.
     */
    private function broadcastProgress(
        int $processedCount,
        int $skippedCount,
        int $warningCount,
        int $errorCount,
        int $total,
        string $currentCharacter,
        bool $force = false,
    ): void {
        $now = microtime(true);

        if (! $force && $this->lastBroadcastAt !== null && ($now - $this->lastBroadcastAt) < 0.25) {
            return;
        }

        $this->lastBroadcastAt = $now;

        GrmUploadProgressed::dispatch(
            $this->userId,
            $processedCount,
            $skippedCount,
            $warningCount,
            $errorCount,
            $total,
            $currentCharacter,
        );
    }

    /**
     * Process a single CSV row.
     *
     * @param  array<string, string>  $row
     */
    protected function processRow(array $row, string $altDelimiter, BlizzardConnector $blizzard): void
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
            $status = $blizzard->send(new GetCharacterStatusRequest(
                $blizzard->defaultRealmSlug(),
                $name,
            ))->dto();
            $characterId = $status->id;
        } catch (BlizzardApiException $e) {
            Log::error('GRM Upload: Could not fetch character data from Blizzard API.', [
                'name' => $name,
                'error' => $e->getMessage(),
            ]);
            throw $e;
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
        BlizzardConnector $blizzard
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
                $altStatus = $blizzard->send(new GetCharacterProfileRequest(
                    $blizzard->defaultRealmSlug(),
                    $altName,
                ))->dto();
                $altId = $altStatus->id;
                $altLevel = $altStatus->level;

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
                Log::debug('GRM Upload: Alt character too low level', [
                    'main' => $mainCharacter->name,
                    'alt' => $altName,
                    'error' => $e->getMessage(),
                ]);

                continue;
            } catch (CharacterNotFoundException|BlizzardApiException $e) {
                Log::debug('GRM Upload: Could not process alt character', [
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

        GrmUploadFailedBroadcast::dispatch($this->userId, $exception->getMessage());

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
