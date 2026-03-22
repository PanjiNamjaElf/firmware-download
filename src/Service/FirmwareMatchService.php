<?php

declare(strict_types=1);

namespace App\Service;

use App\DTO\FirmwareCheckRequest;
use App\Entity\SoftwareVersion;
use App\Repository\SoftwareVersionRepository;
use App\ValueObject\FirmwareResult;
use App\ValueObject\HardwareClass;

/**
 * Core firmware matching logic ported from ConnectedSiteController::softwareDownload().
 *
 * Response messages, match conditions, and iteration order are preserved exactly.
 * Inline "Parity:" comments note specific behaviors carried over from the legacy code.
 */
final class FirmwareMatchService
{
    // HW version regex patterns — preserved verbatim from the legacy controller.
    // LCI patterns must be evaluated before standard patterns (see detectHardwareClass()).

    private const PATTERN_ST = '/^CPAA_[0-9]{4}\.[0-9]{2}\.[0-9]{2}(_[A-Z]+)?$/i';
    private const PATTERN_GD = '/^CPAA_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}(_[A-Z]+)?$/i';
    private const PATTERN_LCI_CIC = '/^B_C_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i';
    private const PATTERN_LCI_NBT = '/^B_N_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i';
    private const PATTERN_LCI_EVO = '/^B_E_G_[0-9]{4}\.[0-9]{2}\.[0-9]{2}$/i';

    public function __construct(
        private readonly SoftwareVersionRepository $repository,
    ) {
        //
    }

    /**
     * Main entry point — mirrors the top-to-bottom flow of the legacy method.
     *
     * Parity: validation short-circuits return only `['msg' => '...']` with no
     * `versionExist` key, matching the legacy partial response shape exactly.
     */
    public function process(FirmwareCheckRequest $request): array
    {
        if (empty($request->version)) {
            return ['msg' => 'Version is required'];
        }

        if (empty($request->hwVersion)) {
            return ['msg' => 'HW Version is required'];
        }

        $hwClass = $this->detectHardwareClass($request->hwVersion);

        if ($hwClass === null) {
            return ['msg' => 'There was a problem identifying your software. Contact us for help.'];
        }

        return $this->findFirmwareResult($request->version, $hwClass)->toArray();
    }

    /**
     * Parses the HW version string and returns the detected hardware class,
     * or null if no known pattern matches.
     *
     * Parity: LCI patterns are checked first, matching the legacy if/elseif order.
     * Reversing would risk mis-classifying an LCI string as standard.
     */
    public function detectHardwareClass(string $hwVersion): ?HardwareClass
    {
        if (preg_match(self::PATTERN_LCI_CIC, $hwVersion)) {
            return new HardwareClass(isSt: true, isGd: false, isLci: true, lciHwType: 'CIC');
        }

        if (preg_match(self::PATTERN_LCI_NBT, $hwVersion)) {
            return new HardwareClass(isSt: false, isGd: true, isLci: true, lciHwType: 'NBT');
        }

        if (preg_match(self::PATTERN_LCI_EVO, $hwVersion)) {
            return new HardwareClass(isSt: false, isGd: true, isLci: true, lciHwType: 'EVO');
        }

        if (preg_match(self::PATTERN_ST, $hwVersion)) {
            return new HardwareClass(isSt: true, isGd: false, isLci: false);
        }

        if (preg_match(self::PATTERN_GD, $hwVersion)) {
            return new HardwareClass(isSt: false, isGd: true, isLci: false);
        }

        return null;
    }

    /**
     * Scans all records in sort_order ASC and returns the first match.
     *
     * Parity: filters in PHP (not SQL) to preserve strcasecmp() semantics from
     * the legacy foreach over softwareversions.json. Breaks on first match.
     */
    public function findFirmwareResult(string $rawVersion, HardwareClass $hwClass): FirmwareResult
    {
        $version = $this->normaliseVersion($rawVersion);

        foreach ($this->repository->findAllOrdered() as $entry) {
            // Parity: strcasecmp() for case-insensitive version matching.
            if (strcasecmp($entry->getSystemVersionAlt(), $version) !== 0) {
                continue;
            }

            // Standard HW must only match standard entries, LCI must only match LCI
            if ($hwClass->isLci !== $entry->isLci()) {
                continue;
            }

            // For LCI, also check that the hardware type (CIC/NBT/EVO) matches the entry
            if ($hwClass->isLci && ! $this->lciHwTypeMatches($hwClass->lciHwType, $entry->getLciHwType())) {
                continue;
            }

            return $this->buildFoundResult($hwClass, $entry);
        }

        return new FirmwareResult(
            versionExist: false,
            msg: 'There was a problem identifying your software. Contact us for help.',
        );
    }

    private function buildFoundResult(HardwareClass $hwClass, SoftwareVersion $entry): FirmwareResult
    {
        if ($entry->isLatest()) {
            return new FirmwareResult(
                versionExist: true,
                msg: 'Your system is upto date!',
            );
        }

        // Derive the latest version label from the DB rather than hardcoding it,
        // so releasing a new version only requires a data change, not a code change.
        $latestLabel = $this->getLatestVersionLabel($entry->getName());

        // Parity: trailing space in the message is verbatim from the legacy system.
        return new FirmwareResult(
            versionExist: true,
            msg: 'The latest version of software is '.$latestLabel.' ',
            link: $entry->getLink() ?? '',
            st: $hwClass->isSt ? ($entry->getStLink() ?? '') : '',
            gd: $hwClass->isGd ? ($entry->getGdLink() ?? '') : '',
        );
    }

    /**
     * Returns the latest version label (e.g. "v3.3.7") for a product line.
     *
     * Extracts just the vX.Y.Z prefix from the full version string to match
     * the legacy message format. The legacy code hardcoded this per-family;
     * here it is read from the database so no code change is needed on release.
     */
    private function getLatestVersionLabel(string $productName): string
    {
        $latest = $this->repository->findOneBy(['name' => $productName, 'isLatest' => true]);

        if ($latest === null) {
            return '';
        }

        // Extract vX.Y.Z prefix — the legacy message used "v3.3.7", not the full string.
        if (preg_match('/^(v\d+\.\d+\.\d+)/i', $latest->getSystemVersion(), $matches)) {
            return $matches[1];
        }

        return $latest->getSystemVersion();
    }

    /**
     * Strips a single leading "v" or "V" from the version string.
     *
     * Parity: mirrors `if (strpos($version, 'v') === 0 || ...) substr($version, 1)`
     * from the legacy controller. Only removes one character.
     */
    private function normaliseVersion(string $version): string
    {
        if (str_starts_with($version, 'v') || str_starts_with($version, 'V')) {
            return substr($version, 1);
        }

        return $version;
    }

    /**
     * Parity: the legacy code used `stripos($row['name'], $lciHwType) === false`.
     * Since we store lciHwType explicitly on each record, strcasecmp is more precise.
     */
    private function lciHwTypeMatches(string $detected, ?string $stored): bool
    {
        if ($stored === null || $stored === '') {
            return false;
        }

        return strcasecmp($detected, $stored) === 0;
    }
}
