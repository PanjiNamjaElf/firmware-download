<?php

declare(strict_types=1);

namespace App\DTO;

/**
 * DTO for the firmware version check request.
 *
 * Wraps the three POST parameters the legacy controller read from $request->request->get().
 * Using a typed DTO keeps the service signature stable regardless of how inputs arrive.
 */
final class FirmwareCheckRequest
{
    /**
     * @param  string  $version     Software version from the device screen. May include a "v" prefix.
     * @param  string  $hwVersion   Hardware version string matched against known HW patterns.
     * @param  string  $mcuVersion  Accepted for backward compatibility but not used in matching.
     */
    public function __construct(
        public readonly string $version,
        public readonly string $hwVersion,
        public readonly string $mcuVersion = '',
    ) {
        //
    }
}
