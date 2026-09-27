<?php

namespace App\Jobs;

use App\Events\Broadcasts\GrmUploadCompleted as GrmUploadCompletedBroadcast;
use App\Events\Broadcasts\GrmUploadFailed as GrmUploadFailedBroadcast;
use App\Events\Broadcasts\GrmUploadProgressed;
use App\Events\Broadcasts\GrmUploadStarted;
use App\Exceptions\CharacterTooLowLevelException;
use App\Http\Integrations\Blizzard\BlizzardConnector;
use App\Http\Integrations\Blizzard\Exceptions\BlizzardRequestException;
use App\Http\Integrations\Blizzard\Exceptions\CharacterNotFoundException;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterProfileRequest;
use App\Http\Integrations\Blizzard\Requests\Character\GetCharacterStatusRequest;
use App\Models\Character;
use App\Models\GameVersion;
use App\Models\GuildRank;
use App\Models\User;
use App\Notifications\GrmUploadCompleted;
use App\Notifications\GrmUploadFailed;
use App\Services\Discord\Notifications\NotifiableChannel;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Timeout;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Queue\Middleware\Skip;
use Illuminate\Support\Facades\Log;

#[Tries(3)]
#[Backoff(60)]
#[Timeout(900)]
class ProcessGrmUpload implements ShouldQueue
{
    use Queueable;

    /**
     * The timestamp of the last progress broadcast, used to throttle updates.
     */
    private ?float $lastBroadcastAt = null;

    private int $processedCount = 0;

    private int $skippedCount = 0;

    private int $warningCount = 0;

    private int $errorCount = 0;

    /**
     * Per-character error messages, sent to Discord in the summary notification.
     *
     * @var array<int, string>
     */
    private array $errors = [];

    /**
     * Create a new job instance.
     *
     * @param  array{delimiter: string, headers: array<int, string>, rows: array<int, array<string, string>>}  $grmData
     */
    public function __construct(
        public array $grmData,
        public string $userId,
        public int $gameVersionId,
    ) {}

    public function middleware(): array
    {
        return [
            Skip::when(empty($this->grmData['rows'])),
        ];
    }

    /**
     * Processes an uploaded GRM roster, keeping the UI updated as it goes,
     * then queues the officer notification and refreshes the guild roster.
     */
    public function handle(BlizzardConnector $blizzard): void
    {
        // Guard against a stale/deleted uploader; fail the job loudly if gone.
        User::findOrFail($this->userId);

        $gameVersion = GameVersion::findOrFail($this->gameVersionId);

        GrmUploadStarted::dispatch($this->userId, $this->total());

        // Suppress model events and timestamp touches while processing rows,
        // since the self-referential alt-character links would otherwise
        // overflow the call stack on a large roster.
        Character::withoutTouching(fn () => Character::withoutEvents(
            fn () => $this->processRows($blizzard, $gameVersion),
        ));

        // Force a final tick so the UI lands on the exact totals.
        $this->broadcastProgress(currentCharacter: '', force: true);

        $this->logSummary();

        $this->notifyOfficers();
        $this->broadcastCompletion();
        $this->refreshRosterIfChanged($gameVersion);
    }

    /**
     * Process every uploaded row, broadcasting progress after each one.
     */
    private function processRows(BlizzardConnector $blizzard, GameVersion $gameVersion): void
    {
        foreach ($this->grmData['rows'] as $row) {
            $this->processRowSafely($row, $blizzard, $gameVersion);

            $this->broadcastProgress($row['Name'] ?? 'Unknown');
        }
    }

    /**
     * Process a single row, tallying the outcome rather than letting a
     * per-character failure abort the whole upload.
     *
     * @param  array<string, string>  $row
     */
    private function processRowSafely(array $row, BlizzardConnector $blizzard, GameVersion $gameVersion): void
    {
        $characterName = $row['Name'] ?? 'Unknown';

        try {
            $this->processRow($row, $this->altDelimiter(), $blizzard, $gameVersion);
            $this->processedCount++;
        } catch (CharacterTooLowLevelException $e) {
            // Below level 10 — skip silently, not an error.
            $this->skippedCount++;
            Log::debug("GRM Upload: Character too low level {$characterName}", [
                'error' => $e->getMessage(),
                'row' => $row,
            ]);
        } catch (CharacterNotFoundException $e) {
            // Blizzard API returned no match — warn but continue.
            $this->warningCount++;
            Log::debug("GRM Upload: Character not found via Blizzard API for {$characterName}", [
                'error' => $e->getMessage(),
                'row' => $row,
            ]);
        } catch (\Exception $e) {
            // Unexpected failure — record for the summary notification.
            $this->errorCount++;
            $this->errors[] = "{$characterName}: {$e->getMessage()}";
            Log::debug("GRM Upload: Failed to process character {$characterName}", [
                'error' => $e->getMessage(),
                'row' => $row,
            ]);
        }
    }

    /**
     * Queue the upload summary for the officer Discord channel.
     */
    private function notifyOfficers(): void
    {
        $channel = NotifiableChannel::stubFromConfig('officer');

        if ($this->errorCount > 0) {
            $channel->notify(new GrmUploadFailed($this->processedCount, $this->errorCount, $this->errors));

            return;
        }

        $channel->notify(new GrmUploadCompleted($this->processedCount, $this->skippedCount, $this->warningCount));
    }

    /**
     * Only broadcast the counts to the UI; the full error detail is sent to
     * Discord instead, since it can be too large to broadcast.
     */
    private function broadcastCompletion(): void
    {
        GrmUploadCompletedBroadcast::dispatch(
            $this->userId,
            $this->processedCount,
            $this->skippedCount,
            $this->warningCount,
            $this->errorCount,
        );
    }

    /**
     * Only refresh the roster when something was actually written; avoids
     * a pointless Blizzard sync on no-op runs.
     */
    private function refreshRosterIfChanged(GameVersion $gameVersion): void
    {
        if ($this->processedCount === 0) {
            return;
        }

        FetchGuildRoster::dispatch($gameVersion->id);
    }

    private function logSummary(): void
    {
        Log::debug('GRM Upload completed', [
            'processed' => $this->processedCount,
            'errors' => $this->errorCount,
            'skipped' => $this->skippedCount,
            'total' => $this->total(),
        ]);
    }

    private function total(): int
    {
        return count($this->grmData['rows']);
    }

    /**
     * GRM exports use one delimiter for columns and the opposite for alt lists.
     */
    private function altDelimiter(): string
    {
        return $this->grmData['delimiter'] === ',' ? ';' : ',';
    }

    /**
     * Broadcast a live progress tick to the uploading user.
     *
     * Throttled to ~4/sec so a large roster doesn't flood the WebSocket; the
     * frontend animates between ticks. Pass force: true for the final tick so
     * the UI lands on the exact totals regardless of timing.
     */
    private function broadcastProgress(string $currentCharacter, bool $force = false): void
    {
        $now = microtime(true);

        if (! $force && $this->lastBroadcastAt !== null && ($now - $this->lastBroadcastAt) < 0.25) {
            return;
        }

        $this->lastBroadcastAt = $now;

        GrmUploadProgressed::dispatch(
            $this->userId,
            $this->processedCount,
            $this->skippedCount,
            $this->warningCount,
            $this->errorCount,
            $this->total(),
            $currentCharacter,
        );
    }

    /**
     * Process a single CSV row.
     *
     * @param  array<string, string>  $row
     */
    private function processRow(array $row, string $altDelimiter, BlizzardConnector $blizzard, GameVersion $gameVersion): void
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
                $gameVersion->realm,
                $name,
                $gameVersion->blizzard_namespace,
            ))->dto();
            $characterId = $status->id;
        } catch (BlizzardRequestException $e) {
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
                'game_version_id' => $gameVersion->id,
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
            $this->processAlts($character, $playerAlts, $altDelimiter, $blizzard, $gameVersion);
        }
    }

    /**
     * Process alt characters and create links.
     */
    private function processAlts(
        Character $mainCharacter,
        string $playerAlts,
        string $altDelimiter,
        BlizzardConnector $blizzard,
        GameVersion $gameVersion,
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
                    $gameVersion->realm,
                    $altName,
                    $gameVersion->blizzard_namespace,
                ))->dto();
                $altId = $altStatus->id;
                $altLevel = $altStatus->level;

                $this->checkCharacterLevel($altName, $altLevel);

                // Find or create the alt character
                $altCharacter = Character::query()->updateOrCreate(
                    ['id' => $altId],
                    ['name' => $altName, 'game_version_id' => $gameVersion->id]
                );

                // Create links in both directions so either character can find the other.
                if (! $altCharacter->linkedCharacters()->where('character_id', $mainCharacter->id)->exists()) {
                    $altCharacter->linkedCharacters()->attach($mainCharacter->id);
                }

                if (! $mainCharacter->linkedCharacters()->where('character_id', $altCharacter->id)->exists()) {
                    $mainCharacter->linkedCharacters()->attach($altCharacter->id);
                }
            } catch (CharacterTooLowLevelException $e) {
                Log::debug('GRM Upload: Alt character too low level', [
                    'main' => $mainCharacter->name,
                    'alt' => $altName,
                    'error' => $e->getMessage(),
                ]);

                continue;
            } catch (CharacterNotFoundException|BlizzardRequestException $e) {
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
    private function checkCharacterLevel(string $name, int $level, int $minLevel = 10): void
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
            NotifiableChannel::stubFromConfig('officer')->notify(
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
