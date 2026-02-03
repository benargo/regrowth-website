<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Requests\Dashboard\UploadGrmDataRequest;
use App\Jobs\ProcessGrmUpload;
use App\Services\Blizzard\GuildService;
use Carbon\Carbon;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class GrmController extends Controller
{
    protected Filesystem $storage;

    public function __construct()
    {
        $this->storage = Storage::disk('local');

        // Make the directory, if it doesn't exist
        $this->storage->makeDirectory('grm/uploads');
        $this->storage->makeDirectory('grm/archives');
    }

    /**
     * Show the GRM data upload form.
     */
    public function showUploadForm(GuildService $guildService)
    {
        if ($this->storage->exists('grm/uploads/latest.csv')) {
            $lastModified = Carbon::createFromTimestamp(
                $this->storage->lastModified('grm/uploads/latest.csv')
            )->format('l, j F Y \a\t H:i');
        } else {
            $lastModified = null;
        }

        return Inertia::render('Dashboard/GRM', [
            'lastUploadTimestamp' => $lastModified,
            'memberCount' => Inertia::defer(fn () => $guildService->members()->count()),
        ]);
    }

    public function handleUpload(UploadGrmDataRequest $request)
    {
        $grmData = $request->input('grm_data');
        $parsedData = $request->getParsedCsvData();

        // Archive and save the raw CSV
        $this->storage->put('grm/archives/'.Carbon::now()->format('Y-m-d_H-i-s').'.csv', $grmData);
        $this->storage->put('grm/uploads/latest.csv', $grmData);

        // Dispatch the processing job
        ProcessGrmUpload::dispatch($parsedData)->withoutDelay();

        return redirect()->route('dashboard.grm-upload.form')->with('success', 'GRM data uploaded successfully. Processing will continue in the background.');
    }
}
