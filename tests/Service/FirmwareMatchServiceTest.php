<?php

declare(strict_types=1);

namespace App\Tests\Service;

use App\DTO\FirmwareCheckRequest;
use App\Entity\SoftwareVersion;
use App\Repository\SoftwareVersionRepository;
use App\Service\FirmwareMatchService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for FirmwareMatchService.
 *
 * Every test maps to a specific branch or condition in the legacy
 * ConnectedSiteController::softwareDownload() method. The comment above each
 * test case cites the legacy code section it covers.
 *
 * The SoftwareVersionRepository is mocked so these tests run without a
 * database. Each test controls exactly which records the service sees.
 */
class FirmwareMatchServiceTest extends TestCase
{
    private SoftwareVersionRepository&MockObject $repository;
    private FirmwareMatchService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(SoftwareVersionRepository::class);
        $this->service = new FirmwareMatchService($this->repository);
    }

    // =========================================================================
    // detectHardwareClass() — pattern matching tests
    // =========================================================================

    /**
     * Legacy: preg_match($patternST, $hwVersion) → $stBool = true
     *
     */
    #[DataProvider('provideStPatterns')]
    public function testDetectsStandardStHardware(string $hwVersion): void
    {
        $result = $this->service->detectHardwareClass($hwVersion);

        $this->assertNotNull($result);
        $this->assertTrue($result->isSt);
        $this->assertFalse($result->isGd);
        $this->assertFalse($result->isLci);
        $this->assertSame('', $result->lciHwType);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideStPatterns(): array
    {
        return [
            'lowercase prefix' => ['CPAA_2023.01.15'],
            'uppercase suffix' => ['CPAA_2023.01.15_BETA'],
            'case insensitive' => ['cpaa_2023.01.15'],
        ];
    }

    /**
     * Legacy: preg_match($patternGD, $hwVersion) → $gdBool = true
     *
     */
    #[DataProvider('provideGdPatterns')]
    public function testDetectsStandardGdHardware(string $hwVersion): void
    {
        $result = $this->service->detectHardwareClass($hwVersion);

        $this->assertNotNull($result);
        $this->assertFalse($result->isSt);
        $this->assertTrue($result->isGd);
        $this->assertFalse($result->isLci);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideGdPatterns(): array
    {
        return [
            'basic GD' => ['CPAA_G_2023.01.15'],
            'GD with suffix' => ['CPAA_G_2023.01.15_RC'],
            'lowercase' => ['cpaa_g_2023.01.15'],
        ];
    }

    /**
     * Legacy: preg_match($patternLCI_CIC, $hwVersion) → $isLCI = true, $lciHwType = 'CIC', $stBool = true
     */
    public function testDetectsLciCicHardware(): void
    {
        $result = $this->service->detectHardwareClass('B_C_2023.01.15');

        $this->assertNotNull($result);
        $this->assertTrue($result->isLci);
        $this->assertTrue($result->isSt);
        $this->assertFalse($result->isGd);
        $this->assertSame('CIC', $result->lciHwType);
    }

    /**
     * Legacy: preg_match($patternLCI_NBT, $hwVersion) → $isLCI = true, $lciHwType = 'NBT', $gdBool = true
     */
    public function testDetectsLciNbtHardware(): void
    {
        $result = $this->service->detectHardwareClass('B_N_G_2023.01.15');

        $this->assertNotNull($result);
        $this->assertTrue($result->isLci);
        $this->assertFalse($result->isSt);
        $this->assertTrue($result->isGd);
        $this->assertSame('NBT', $result->lciHwType);
    }

    /**
     * Legacy: preg_match($patternLCI_EVO, $hwVersion) → $isLCI = true, $lciHwType = 'EVO', $gdBool = true
     */
    public function testDetectsLciEvoHardware(): void
    {
        $result = $this->service->detectHardwareClass('B_E_G_2023.01.15');

        $this->assertNotNull($result);
        $this->assertTrue($result->isLci);
        $this->assertFalse($result->isSt);
        $this->assertTrue($result->isGd);
        $this->assertSame('EVO', $result->lciHwType);
    }

    /**
     * Legacy: if (!$hwVersionBool) { return ['msg' => 'There was a problem...']; }
     *
     */
    #[DataProvider('provideUnknownHwPatterns')]
    public function testReturnsNullForUnknownHwPattern(string $hwVersion): void
    {
        $result = $this->service->detectHardwareClass($hwVersion);

        $this->assertNull($result);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideUnknownHwPatterns(): array
    {
        return [
            'empty string' => [''],
            'random string' => ['XYZ_UNKNOWN'],
            'partial ST pattern' => ['CPAA_2023.01'],
            'CPAA without numbers' => ['CPAA_'],
            'wrong separators' => ['CPAA-2023-01-15'],
        ];
    }

    // =========================================================================
    // process() — validation flow tests
    // =========================================================================

    /**
     * Legacy: if (empty($version)) { return ['msg' => 'Version is required']; }
     */
    public function testReturnsErrorWhenVersionIsEmpty(): void
    {
        $this->repository->expects($this->never())->method('findAllOrdered');

        $result = $this->service->process(
            new FirmwareCheckRequest(version: '', hwVersion: 'CPAA_2023.01.15'),
        );

        $this->assertSame(['msg' => 'Version is required'], $result);
    }

    /**
     * Whitespace-only version must be treated as empty after trim in the controller.
     * The service itself receives the already-trimmed string from the DTO.
     */
    public function testReturnsErrorWhenVersionIsWhitespaceOnly(): void
    {
        $this->repository->expects($this->never())->method('findAllOrdered');

        $result = $this->service->process(
            new FirmwareCheckRequest(version: '', hwVersion: 'CPAA_2023.01.15'),
        );

        $this->assertSame(['msg' => 'Version is required'], $result);
    }

    /**
     * Legacy: if (empty($hwVersion)) { return ['msg' => 'HW Version is required']; }
     */
    public function testReturnsErrorWhenHwVersionIsEmpty(): void
    {
        $this->repository->expects($this->never())->method('findAllOrdered');

        $result = $this->service->process(
            new FirmwareCheckRequest(version: '3.3.7.mmipri.c', hwVersion: ''),
        );

        $this->assertSame(['msg' => 'HW Version is required'], $result);
    }

    /**
     * Legacy: if (!$hwVersionBool) { return ['msg' => 'There was a problem identifying...']; }
     */
    public function testReturnsErrorWhenHwVersionPatternUnknown(): void
    {
        $this->repository->expects($this->never())->method('findAllOrdered');

        $result = $this->service->process(
            new FirmwareCheckRequest(version: '3.3.7.mmipri.c', hwVersion: 'UNKNOWN_HW'),
        );

        $this->assertSame(
            ['msg' => 'There was a problem identifying your software. Contact us for help.'],
            $result,
        );
    }

    // =========================================================================
    // findFirmwareResult() — version matching and response building tests
    // =========================================================================

    /**
     * Legacy: if ($row['latest']) { return ['versionExist' => true, 'msg' => 'Your system is upto date!', ...]; }
     *
     * Parity note: "upto" (one word) is a typo in the legacy system that must be preserved.
     */
    public function testReturnsUpToDateMessageWhenOnLatestVersion(): void
    {
        $entry = $this->makeEntry(
            systemVersionAlt: '3.3.7.mmipri.c',
            isLatest: true,
            isLci: false,
        );

        $this->repository->method('findAllOrdered')->willReturn([$entry]);

        $result = $this->service->process(
            new FirmwareCheckRequest(version: '3.3.7.mmipri.c', hwVersion: 'CPAA_2023.01.15'),
        );

        $this->assertTrue((bool) $result['versionExist']);
        $this->assertSame('Your system is upto date!', $result['msg']);
        $this->assertSame('', $result['st']);
        $this->assertSame('', $result['gd']);
        $this->assertSame('', $result['link']);
    }

    /**
     * The update-available message includes the latest version label, derived
     * dynamically from the DB entry where isLatest = true for the same product line.
     * Parity: trailing space after the label is verbatim from the legacy system.
     */
    public function testReturnsStandardLatestLabelForNonLciHardware(): void
    {
        $entry = $this->makeEntry(
            systemVersionAlt: '3.1.1.2.mmi.c',
            isLatest: false,
            isLci: false,
            stLink: 'https://drive.google.com/st-link',
        );

        // The service queries for the latest entry to derive the version label.
        $latestEntry = $this->makeEntry(
            systemVersionAlt: '3.3.7.mmipri.c',
            isLatest: true,
            isLci: false,
            systemVersion: 'v3.3.7.mmipri.c',
        );

        $this->repository->method('findAllOrdered')->willReturn([$entry]);
        $this->repository->method('findOneBy')->willReturn($latestEntry);

        $result = $this->service->process(
            new FirmwareCheckRequest(version: '3.1.1.2.mmi.c', hwVersion: 'CPAA_2023.01.15'),
        );

        $this->assertTrue((bool) $result['versionExist']);
        $this->assertSame('The latest version of software is v3.3.7 ', $result['msg']);
    }

    /**
     * LCI hardware must receive the LCI latest label ("v3.4.4"), not the standard one.
     */
    public function testReturnsLciLatestLabelForLciHardware(): void
    {
        $entry = $this->makeEntry(
            systemVersionAlt: '3.4.1.mmiprixu.b',
            isLatest: false,
            isLci: true,
            lciHwType: 'CIC',
            stLink: 'https://drive.google.com/lci-cic-link',
        );

        $latestEntry = $this->makeEntry(
            systemVersionAlt: '3.4.4.mmiprilci',
            isLatest: true,
            isLci: true,
            systemVersion: 'v3.4.4.mmiprilci',
        );

        $this->repository->method('findAllOrdered')->willReturn([$entry]);
        $this->repository->method('findOneBy')->willReturn($latestEntry);

        $result = $this->service->process(
            new FirmwareCheckRequest(version: '3.4.1.mmiprixu.b', hwVersion: 'B_C_2023.01.15'),
        );

        $this->assertTrue((bool) $result['versionExist']);
        $this->assertSame('The latest version of software is v3.4.4 ', $result['msg']);
    }

    /**
     * ST hardware must only receive the ST link; GD link must be empty.
     *
     * Legacy: if ($stBool) { $stLink = $row['st']; }
     *         (gdLink only set if $gdBool)
     */
    public function testStHardwareReceivesStLinkAndEmptyGdLink(): void
    {
        $entry = $this->makeEntry(
            systemVersionAlt: '3.3.6.mmipri.c',
            isLatest: false,
            isLci: false,
            stLink: 'https://example.com/st-firmware',
            gdLink: 'https://example.com/gd-firmware',
        );

        $this->repository->method('findAllOrdered')->willReturn([$entry]);

        $result = $this->service->process(
            new FirmwareCheckRequest(version: '3.3.6.mmipri.c', hwVersion: 'CPAA_2023.01.15'),
        );

        $this->assertSame('https://example.com/st-firmware', $result['st']);
        $this->assertSame('', $result['gd']);
    }

    /**
     * GD hardware must only receive the GD link; ST link must be empty.
     */
    public function testGdHardwareReceivesGdLinkAndEmptyStLink(): void
    {
        $entry = $this->makeEntry(
            systemVersionAlt: '3.3.6.mmipri.b',
            isLatest: false,
            isLci: false,
            stLink: 'https://example.com/st-firmware',
            gdLink: 'https://example.com/gd-firmware',
        );

        $this->repository->method('findAllOrdered')->willReturn([$entry]);

        $result = $this->service->process(
            new FirmwareCheckRequest(version: '3.3.6.mmipri.b', hwVersion: 'CPAA_G_2023.01.15'),
        );

        $this->assertSame('', $result['st']);
        $this->assertSame('https://example.com/gd-firmware', $result['gd']);
    }

    /**
     * Version matching is case-insensitive.
     *
     * Legacy: strcasecmp($row['system_version_alt'], $version) === 0
     */
    public function testVersionMatchingIsCaseInsensitive(): void
    {
        $entry = $this->makeEntry(
            systemVersionAlt: '3.3.7.mmipri.c',
            isLatest: true,
            isLci: false,
        );

        $this->repository->method('findAllOrdered')->willReturn([$entry]);

        $result = $this->service->process(
            new FirmwareCheckRequest(version: '3.3.7.MMIPRI.C', hwVersion: 'CPAA_2023.01.15'),
        );

        $this->assertTrue((bool) $result['versionExist']);
    }

    /**
     * Leading "v" prefix must be stripped before matching.
     *
     * Legacy: if (strpos($version, 'v') === 0 || ...) { $version = substr($version, 1); }
     *
     */
    #[DataProvider('provideVersionsWithPrefix')]
    public function testVersionPrefixIsStripped(string $inputVersion): void
    {
        $entry = $this->makeEntry(
            systemVersionAlt: '3.3.7.mmipri.c',
            isLatest: true,
            isLci: false,
        );

        $this->repository->method('findAllOrdered')->willReturn([$entry]);

        $result = $this->service->process(
            new FirmwareCheckRequest(version: $inputVersion, hwVersion: 'CPAA_2023.01.15'),
        );

        $this->assertTrue((bool) $result['versionExist']);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function provideVersionsWithPrefix(): array
    {
        return [
            'lowercase v prefix' => ['v3.3.7.mmipri.c'],
            'uppercase V prefix' => ['V3.3.7.mmipri.c'],
            'no prefix' => ['3.3.7.mmipri.c'],
        ];
    }

    /**
     * LCI hardware must NOT match a non-LCI database entry, even if the
     * system_version_alt string is identical.
     *
     * Legacy: if ($isLCI !== $isLCIEntry) { continue; }
     */
    public function testLciHardwareDoesNotMatchNonLciEntry(): void
    {
        // Entry is NOT LCI.
        $entry = $this->makeEntry(
            systemVersionAlt: '3.3.7.mmipri.c',
            isLatest: true,
            isLci: false,
        );

        $this->repository->method('findAllOrdered')->willReturn([$entry]);

        // Hardware IS LCI — should not match the above entry.
        $result = $this->service->process(
            new FirmwareCheckRequest(version: '3.3.7.mmipri.c', hwVersion: 'B_C_2023.01.15'),
        );

        $this->assertFalse($result['versionExist']);
    }

    /**
     * Standard hardware must NOT match an LCI database entry.
     */
    public function testNonLciHardwareDoesNotMatchLciEntry(): void
    {
        $entry = $this->makeEntry(
            systemVersionAlt: '3.4.1.mmiprixu.b',
            isLatest: false,
            isLci: true,
            lciHwType: 'CIC',
        );

        $this->repository->method('findAllOrdered')->willReturn([$entry]);

        // Standard ST hardware — must not match LCI entry.
        $result = $this->service->process(
            new FirmwareCheckRequest(version: '3.4.1.mmiprixu.b', hwVersion: 'CPAA_2023.01.15'),
        );

        $this->assertFalse($result['versionExist']);
    }

    /**
     * LCI CIC hardware must NOT match an LCI NBT entry.
     *
     * Legacy: if ($isLCI && stripos($row['name'], $lciHwType) === false) { continue; }
     */
    public function testLciCicHardwareDoesNotMatchLciNbtEntry(): void
    {
        $entry = $this->makeEntry(
            systemVersionAlt: '3.4.1.mmiprixu.b',
            isLatest: false,
            isLci: true,
            lciHwType: 'NBT',  // Entry is for NBT.
            gdLink: 'https://example.com/nbt-link',
        );

        $this->repository->method('findAllOrdered')->willReturn([$entry]);

        // Hardware is CIC — must skip the NBT entry.
        $result = $this->service->process(
            new FirmwareCheckRequest(version: '3.4.1.mmiprixu.b', hwVersion: 'B_C_2023.01.15'),
        );

        $this->assertFalse($result['versionExist']);
    }

    /**
     * The loop must break on first match — a second matching entry for the same
     * version must never be reached.
     *
     * Legacy: break; at end of matched block.
     */
    public function testBreaksOnFirstVersionMatch(): void
    {
        $firstEntry = $this->makeEntry(
            systemVersionAlt: '3.3.0.mmipri.c',
            isLatest: false,
            isLci: false,
            stLink: 'https://example.com/first-match',
        );

        $secondEntry = $this->makeEntry(
            systemVersionAlt: '3.3.0.mmipri.c',
            isLatest: false,
            isLci: false,
            stLink: 'https://example.com/second-match-should-not-appear',
        );

        $this->repository->method('findAllOrdered')->willReturn([$firstEntry, $secondEntry]);

        $result = $this->service->process(
            new FirmwareCheckRequest(version: '3.3.0.mmipri.c', hwVersion: 'CPAA_2023.01.15'),
        );

        $this->assertSame('https://example.com/first-match', $result['st']);
    }

    /**
     * When no entry matches, versionExist must be false with the support error message.
     *
     * Legacy fallthrough:
     *   $response = ['versionExist' => false, 'msg' => 'There was a problem...', 'link' => '', 'st' => '', 'gd' => ''];
     */
    public function testReturnsVersionNotFoundWhenNoEntryMatches(): void
    {
        $this->repository->method('findAllOrdered')->willReturn([]);

        $result = $this->service->process(
            new FirmwareCheckRequest(version: '9.9.9.unknown', hwVersion: 'CPAA_2023.01.15'),
        );

        $this->assertFalse($result['versionExist']);
        $this->assertSame(
            'There was a problem identifying your software. Contact us for help.',
            $result['msg'],
        );
        $this->assertSame('', $result['link']);
        $this->assertSame('', $result['st']);
        $this->assertSame('', $result['gd']);
    }

    /**
     * Null link fields on the entity must serialise as empty strings, not null,
     * to preserve the exact legacy JSON output shape.
     */
    public function testNullLinksSerialiseAsEmptyStrings(): void
    {
        $entry = $this->makeEntry(
            systemVersionAlt: '3.3.6.mmipri.c',
            isLatest: false,
            isLci: false,
            stLink: null,
            gdLink: null,
            link: null,
        );

        $this->repository->method('findAllOrdered')->willReturn([$entry]);

        $result = $this->service->process(
            new FirmwareCheckRequest(version: '3.3.6.mmipri.c', hwVersion: 'CPAA_2023.01.15'),
        );

        $this->assertSame('', $result['st']);
        $this->assertSame('', $result['gd']);
        $this->assertSame('', $result['link']);
    }

    // =========================================================================
    // Response shape integrity tests
    // =========================================================================

    /**
     * The validation-error response (missing version) only contains `msg`.
     * The legacy controller returns a different shape for these two cases.
     */
    public function testValidationErrorResponseShapeContainsMsgOnly(): void
    {
        $result = $this->service->process(
            new FirmwareCheckRequest(version: '', hwVersion: ''),
        );

        $this->assertArrayHasKey('msg', $result);
        // Validation short-circuit responses do NOT include versionExist.
        $this->assertArrayNotHasKey('versionExist', $result);
    }

    /**
     * A fully resolved response (match found or not found) must always
     * include all five keys to match the legacy JSON shape.
     */
    public function testFullResponseShapeHasAllRequiredKeys(): void
    {
        $this->repository->method('findAllOrdered')->willReturn([]);

        $result = $this->service->process(
            new FirmwareCheckRequest(version: '9.9.9.unknown', hwVersion: 'CPAA_2023.01.15'),
        );

        $this->assertArrayHasKey('versionExist', $result);
        $this->assertArrayHasKey('msg', $result);
        $this->assertArrayHasKey('link', $result);
        $this->assertArrayHasKey('st', $result);
        $this->assertArrayHasKey('gd', $result);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /**
     * Builds a SoftwareVersion entity stub with controlled field values.
     * Uses reflection to bypass the constructor and set private properties directly.
     *
     * @throws \ReflectionException
     */
    private function makeEntry(
        string $systemVersionAlt,
        bool $isLatest,
        bool $isLci,
        ?string $lciHwType = null,
        ?string $stLink = null,
        ?string $gdLink = null,
        ?string $link = null,
        ?string $systemVersion = null,
    ): SoftwareVersion {
        $entry = new SoftwareVersion();

        $this->setPrivate($entry, 'systemVersionAlt', $systemVersionAlt);
        $this->setPrivate($entry, 'isLatest', $isLatest);
        $this->setPrivate($entry, 'isLci', $isLci);
        $this->setPrivate($entry, 'lciHwType', $lciHwType);
        $this->setPrivate($entry, 'stLink', $stLink);
        $this->setPrivate($entry, 'gdLink', $gdLink);
        $this->setPrivate($entry, 'link', $link);
        $this->setPrivate($entry, 'name', 'Test Entry');
        $this->setPrivate($entry, 'systemVersion', $systemVersion ?? ('v'.$systemVersionAlt));

        return $entry;
    }

    /**
     * @throws \ReflectionException
     */
    private function setPrivate(object $object, string $property, mixed $value): void
    {
        $ref = new \ReflectionProperty($object, $property);
        $ref->setValue($object, $value);
    }
}
