<?php

namespace App\Http\Integrations\WarcraftLogs\Exceptions;

use Throwable;

/**
 * Marker for every exception the Warcraft Logs connector throws, so callers can catch one type.
 */
interface WarcraftLogsRequestException extends Throwable {}
