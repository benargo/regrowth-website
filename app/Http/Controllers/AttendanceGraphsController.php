<?php

namespace App\Http\Controllers;

use App\Http\Resources\AttendanceScatterPointResource;
use App\Services\Attendance\Calculator;
use App\Services\Attendance\CharacterAttendanceRowData;
use App\Services\Attendance\DataTable;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AttendanceGraphsController extends Controller
{
    public function __construct(
        private readonly Calculator $calculator,
        private DataTable $table,
    ) {}

    public function index(Request $request): Response
    {
        return Inertia::render('Raiding/Attendance/Graphs', [
            'scatterPoints' => Inertia::defer(function () use ($request) {
                return AttendanceScatterPointResource::collection($this->table
                    ->rows()
                    ->transform(fn (CharacterAttendanceRowData $row) => new AttendanceScatterPointResource($row))
                    ->values()
                )->resolve($request);
            }),
        ]);
    }
}
