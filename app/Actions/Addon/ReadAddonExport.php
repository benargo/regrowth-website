<?php

namespace App\Actions\Addon;

use App\Models\GameVersion;
use App\Models\User;
use Illuminate\Filesystem\FilesystemManager;
use Lorisleiva\Actions\Concerns\AsAction;

class ReadAddonExport
{
    use AsAction;

    public function __construct(protected FilesystemManager $storage) {}

    /**
     * Read the game version's addon export from storage, injecting the user into its system data.
     * Returns null when the export is missing or unreadable.
     *
     * @return array<string, mixed>|null
     */
    public function handle(GameVersion $gameVersion, User $user): ?array
    {
        $json = $this->storage->disk('addon')->get("{$gameVersion->id}/export.json");

        if ($json === null) {
            return null;
        }

        $data = json_decode($json, true);

        if (! is_array($data)) {
            return null;
        }

        $data['system']['user'] = [
            'id' => $user->id,
            'name' => $user->displayName,
        ];

        return $data;
    }
}
