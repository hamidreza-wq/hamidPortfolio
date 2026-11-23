<?php

namespace App\Services\Analytics;

use App\Services\Platform\IntegrationFailure;

/**
 * Google rejected the stored refresh token (expired, revoked or issued to a
 * different OAuth client). Only a fresh consent can recover the connection.
 */
class SearchConsoleReauthorizationRequired extends IntegrationFailure {}
