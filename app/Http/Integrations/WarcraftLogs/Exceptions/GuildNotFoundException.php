<?php

namespace App\Http\Integrations\WarcraftLogs\Exceptions;

/**
 * The requested guild (or its attendance) does not exist on the namespace host.
 * Thrown by the guild requests' own getRequestException() (Phase 3).
 */
class GuildNotFoundException extends GraphQLException {}
