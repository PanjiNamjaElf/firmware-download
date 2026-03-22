<?php

declare(strict_types=1);

namespace App\ValueObject;

/**
 * Immutable result of HW version pattern detection.
 *
 * Replaces the four separate booleans in the legacy controller
 * ($stBool, $gdBool, $isLCI, $lciHwType) with a typed value object.
 */
final class HardwareClass
{
    /**
     * @param  bool  $isSt   True if hardware uses the STMicroelectronics variant.
     * @param  bool  $isGd   True if hardware uses the GigaDevice variant.
     * @param  bool  $isLci  True if hardware belongs to the LCI product family.
     * @param  string  $lciHwType  For LCI devices: 'CIC', 'NBT', or 'EVO'. Empty string for standard.
     */
    public function __construct(
        public readonly bool $isSt,
        public readonly bool $isGd,
        public readonly bool $isLci,
        public readonly string $lciHwType = '',
    ) {
        //
    }
}
