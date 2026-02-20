<?php

namespace App\Jobs;

use App\Exceptions\CharacterTooLowLevelException;
use App\Models\Character;
use App\Models\GuildRank;
use App\Notifications\DiscordNotifiable;
use App\Notifications\GrmUploadCompleted;
use App\Notifications\GrmUploadFailed;
use App\Services\Blizzard\CharacterService;
use App\Services\Blizzard\Exceptions\CharacterNotFoundException;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
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
     * Execute the job.
     */
    public function handle(CharacterService $characterService): void
    {
        $delimiter = $this->grmData['delimiter'];
        $altDelimiter = $delimiter === ',' ? ';' : ',';
        $rows = $this->grmData['rows'];

        $processedCount = 0;
        $errorCount = 0;
        $errors = [];
        $warningCount = 0;
        $skippedCount = 0;

        foreach ($rows as $row) {
            try {
                $this->processRow($row, $altDelimiter, $characterService);
                $processedCount++;
            } catch (CharacterTooLowLevelException $e) {
                $skippedCount++;
                $characterName = $row['Name'] ?? 'Unknown';
                Log::notice("GRM Upload: Character too low level {$characterName}", [
                    'error' => $e->getMessage(),
                    'row' => $row,
                ]);
            } catch (CharacterNotFoundException $e) {
                $warningCount++;
                $characterName = $row['Name'] ?? 'Unknown';
                Log::warning("GRM Upload: Character not found via Blizzard API for {$characterName}", [
                    'error' => $e->getMessage(),
                    'row' => $row,
                ]);
            } catch (\Exception $e) {
                $errorCount++;
                $characterName = $row['Name'] ?? 'Unknown';
                $errors[] = "{$characterName}: {$e->getMessage()}";
                Log::warning("GRM Upload: Failed to process character {$characterName}", [
                    'error' => $e->getMessage(),
                    'row' => $row,
                ]);
            }
        }

        Log::info('GRM Upload completed', [
            'processed' => $processedCount,
            'errors' => $errorCount,
            'skipped' => $skippedCount,
            'total' => count($rows),
        ]);

        if ($errorCount > 0) {
            DiscordNotifiable::officer()->notify(
                new GrmUploadFailed($processedCount, $errorCount, $errors)
            );
        } else {
            DiscordNotifiable::officer()->notify(
                new GrmUploadCompleted($processedCount, $skippedCount, $warningCount)
            );
        }
    }

    /**
     * Process a single CSV row.
     *
     * @param  array<string, string>  $row
     */
    protected function processRow(array $row, string $altDelimiter, CharacterService $characterService): void
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
            $status = $characterService->getStatus($name);
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
            $this->processAlts($character, $playerAlts, $altDelimiter, $characterService);
        }
    }

    /**
     * Process alt characters and create links.
     */
    protected function processAlts(
        Character $mainCharacter,
        string $playerAlts,
        string $altDelimiter,
        CharacterService $characterService
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
                $altStatus = $characterService->getProfile($altName);
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

        try {
            DiscordNotifiable::officer()->notifyNow(
                new GrmUploadFailed(0, 1, [], $exception->getMessage())
            );
        } catch (\Exception $e) {
            Log::error('GRM Upload: Failed to send failure notification', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
