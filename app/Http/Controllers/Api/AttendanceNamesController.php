<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Raid\AttendanceMatrixRequest;
use App\Services\Attendance\Calculator;
use App\Services\Attendance\CharacterAttendanceRowData;
use App\Services\Attendance\DataTable;
use Illuminate\Http\JsonResponse;

class AttendanceNamesController extends Controller
{
    public function __construct(
        protected readonly Calculator $calculator
    ) {}

    /**
     * Return the attendanceNames array for a single character row.
     *
     * @return JsonResponse Body: array<int, array<string>>|null
     */
    public function __invoke(AttendanceMatrixRequest $request): JsonResponse
    {
        $characterId = (int) $request->query('character_id');

        if (! $characterId) {
            return response()->json(null);
        }

        $filters = $request->filters();
        $table = new DataTable($this->calculator, $filters);
        $rows = $table->mergedRows();

        $row = $rows->first(fn (CharacterAttendanceRowData $row) => $row->character->id === $characterId);

        return response()->json($row?->attendanceNames);
    }
}
