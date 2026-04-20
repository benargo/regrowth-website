<?php

namespace App\Http\Resources\Raid;

use Illuminate\Http\Resources\Json\ResourceCollection;

class AttendanceScatterPointCollection extends ResourceCollection
{
    /**
     * The resource that this resource collects.
     *
     * @var string
     */
    public $collects = AttendanceScatterPointResource::class;
}
