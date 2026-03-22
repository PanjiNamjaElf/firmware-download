<?php

declare(strict_types=1);

namespace App\ValueObject;

/**
 * Immutable response payload returned by FirmwareMatchService.
 *
 * versionExist drives the frontend display:
 *   - true  + empty links → already on latest (green, no download link)
 *   - true  + links       → update available (green, show ST/GD links)
 *   - false               → version not found (red, contact support)
 *
 * All string fields default to empty string so the JSON shape always matches
 * what the legacy frontend expects.
 */
final class FirmwareResult
{
    /**
     * @param  bool    $versionExist  Whether the version was found in the database.
     * @param  string  $msg           User-facing message (verbatim from legacy strings).
     * @param  string  $link          General download folder. Empty when not applicable.
     * @param  string  $st            ST download link. Empty when not applicable.
     * @param  string  $gd            GD download link. Empty when not applicable.
     */
    public function __construct(
        public readonly bool $versionExist,
        public readonly string $msg,
        public readonly string $link = '',
        public readonly string $st = '',
        public readonly string $gd = '',
    ) {
        //
    }

    /**
     * Returns the array structure the frontend reads by key name.
     * Key names must not change — the legacy frontend checks versionExist, msg, st, and gd.
     *
     * @return array{versionExist: bool, msg: string, link: string, st: string, gd: string}
     */
    public function toArray(): array
    {
        return [
            'versionExist' => $this->versionExist,
            'msg' => $this->msg,
            'link' => $this->link,
            'st' => $this->st,
            'gd' => $this->gd,
        ];
    }
}
