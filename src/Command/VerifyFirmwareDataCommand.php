<?php

declare(strict_types=1);

namespace App\Command;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Read-only verification command.
 *
 * Runs a set of assertions against the imported firmware data to confirm
 * that all records are present and correctly structured. Does not
 * write to the database.
 *
 * Usage:
 *   php bin/console app:verify-firmware-data
 *
 * All checks pass → exit code 0.
 * Any check fails → exit code 1, with a clear failure message.
 */
#[AsCommand(
    name: 'app:verify-firmware-data',
    description: 'Verifies imported firmware data matches expected values from the legacy JSON.',
)]
class VerifyFirmwareDataCommand extends Command
{
    /**
     * Expected values derived from the legacy JSON file.
     * These are fixed constants — not read from the DB — so any deviation
     * from the expected import is caught immediately.
     */
    private const EXPECTED_TOTAL = 116;
    private const EXPECTED_STANDARD = 98; // 116 - 18 LCI entries
    private const EXPECTED_LCI = 18;
    private const EXPECTED_LATEST_COUNT = 12; // One per product line
    private const EXPECTED_PRODUCT_LINES = [
        'MMI Prime CIC',
        'MMI Prime NBT',
        'MMI Prime EVO',
        'MMI PRO CIC',
        'MMI PRO NBT',
        'MMI PRO EVO',
        'LCI MMI Prime CIC',
        'LCI MMI Prime NBT',
        'LCI MMI Prime EVO',
        'LCI MMI PRO CIC',
        'LCI MMI PRO NBT',
        'LCI MMI PRO EVO',
    ];

    /**
     * Expected sort_order value for specific well-known records.
     * Used to verify that iteration order was preserved exactly.
     */
    private const EXPECTED_SORT_ORDERS = [
        // First record in the JSON.
        ['name' => 'MMI Prime CIC', 'versionAlt' => '3.1.1.2.mmi.c', 'sortOrder' => 0],
        // Latest entry for the first standard product line.
        ['name' => 'MMI Prime CIC', 'versionAlt' => '3.3.7.mmipri.c', 'sortOrder' => 18],
        // First LCI entry.
        ['name' => 'LCI MMI Prime CIC', 'versionAlt' => '3.4.1.mmiprixu.b', 'sortOrder' => 98],
        // Last record in the JSON.
        ['name' => 'LCI MMI PRO EVO', 'versionAlt' => '3.4.4.mmiprolci', 'sortOrder' => 115],
    ];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    /**
     * @param  \Symfony\Component\Console\Input\InputInterface  $input  The command input.
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output  The command output.
     * @return int 0 on success, 1 on failure.
     * @throws \Doctrine\ORM\NonUniqueResultException|\Doctrine\ORM\NoResultException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $passed = 0;
        $failed = 0;

        $io->title('Firmware Data Verification');

        $checks = [
            $this->checkTotalCount(),
            $this->checkStandardCount(),
            $this->checkLciCount(),
            $this->checkLatestCount(),
            $this->checkAllProductLinesPresent(),
            $this->checkOneLatestPerProductLine(),
            $this->checkLciHwTypeCompleteness(),
            $this->checkSortOrders(),
            $this->checkKnownLatestVersions(),
            $this->checkKnownDownloadLinks(),
        ];

        foreach ($checks as ['pass' => $pass]) {
            $pass ? ++$passed : ++$failed;
        }

        // Summary table.
        $this->renderSummaryTable($output, $checks);

        $io->newLine();

        if ($failed === 0) {
            $io->success(sprintf('All %d checks passed. Import data is correct.', $passed));

            return Command::SUCCESS;
        }

        $io->error(sprintf('%d of %d checks failed. Review the output above.', $failed, $passed + $failed));

        return Command::FAILURE;
    }

    // -------------------------------------------------------------------------
    // Individual checks
    // -------------------------------------------------------------------------

    /**
     * @return array{name: string, pass: bool, detail: string}
     * @throws \Doctrine\ORM\NonUniqueResultException|\Doctrine\ORM\NoResultException
     */
    private function checkTotalCount(): array
    {
        $actual = $this->entityManager
            ->createQuery('SELECT COUNT(sv.id) FROM App\Entity\SoftwareVersion sv')
            ->getSingleScalarResult();

        $pass = (int) $actual === self::EXPECTED_TOTAL;

        return [
            'name' => 'Total record count',
            'pass' => $pass,
            'detail' => sprintf('Expected %d, got %d', self::EXPECTED_TOTAL, $actual),
        ];
    }

    /**
     * @return array{name: string, pass: bool, detail: string}
     * @throws \Doctrine\ORM\NonUniqueResultException|\Doctrine\ORM\NoResultException
     */
    private function checkStandardCount(): array
    {
        $actual = $this->entityManager
            ->createQuery('SELECT COUNT(sv.id) FROM App\Entity\SoftwareVersion sv WHERE sv.isLci = false')
            ->getSingleScalarResult();

        $pass = (int) $actual === self::EXPECTED_STANDARD;

        return [
            'name' => 'Standard (non-LCI) record count',
            'pass' => $pass,
            'detail' => sprintf('Expected %d, got %d', self::EXPECTED_STANDARD, $actual),
        ];
    }

    /**
     * @return array{name: string, pass: bool, detail: string}
     * @throws \Doctrine\ORM\NonUniqueResultException|\Doctrine\ORM\NoResultException
     */
    private function checkLciCount(): array
    {
        $actual = $this->entityManager
            ->createQuery('SELECT COUNT(sv.id) FROM App\Entity\SoftwareVersion sv WHERE sv.isLci = true')
            ->getSingleScalarResult();

        $pass = (int) $actual === self::EXPECTED_LCI;

        return [
            'name' => 'LCI record count',
            'pass' => $pass,
            'detail' => sprintf('Expected %d, got %d', self::EXPECTED_LCI, $actual),
        ];
    }

    /**
     * @return array{name: string, pass: bool, detail: string}
     * @throws \Doctrine\ORM\NonUniqueResultException|\Doctrine\ORM\NoResultException
     */
    private function checkLatestCount(): array
    {
        $actual = $this->entityManager
            ->createQuery('SELECT COUNT(sv.id) FROM App\Entity\SoftwareVersion sv WHERE sv.isLatest = true')
            ->getSingleScalarResult();

        $pass = (int) $actual === self::EXPECTED_LATEST_COUNT;

        return [
            'name' => '"Is Latest" flag count',
            'pass' => $pass,
            'detail' => sprintf('Expected %d (one per product line), got %d', self::EXPECTED_LATEST_COUNT, $actual),
        ];
    }

    /**
     * @return array{name: string, pass: bool, detail: string}
     */
    private function checkAllProductLinesPresent(): array
    {
        $names = $this->entityManager
            ->createQuery('SELECT DISTINCT sv.name FROM App\Entity\SoftwareVersion sv ORDER BY sv.name ASC')
            ->getSingleColumnResult();

        $missing = array_diff(self::EXPECTED_PRODUCT_LINES, $names);
        $pass = count($missing) === 0;

        return [
            'name' => 'All 12 product lines present',
            'pass' => $pass,
            'detail' => $pass
                ? '12 product lines found'
                : 'Missing: '.implode(', ', $missing),
        ];
    }

    /**
     * @return array{name: string, pass: bool, detail: string}
     */
    private function checkOneLatestPerProductLine(): array
    {
        $rows = $this->entityManager
            ->createQuery(
                'SELECT sv.name, COUNT(sv.id) AS cnt
                FROM App\Entity\SoftwareVersion sv
                WHERE sv.isLatest = true
                GROUP BY sv.name
                HAVING COUNT(sv.id) > 1',
            )
            ->getArrayResult();

        $pass = count($rows) === 0;

        $detail = $pass
            ? 'Each product line has exactly one "latest" entry'
            : 'Multiple "latest" entries in: '.implode(', ', array_column($rows, 'name'));

        return [
            'name' => 'One "latest" entry per product line',
            'pass' => $pass,
            'detail' => $detail,
        ];
    }

    /**
     * Checks that every LCI record has a non-null lci_hw_type.
     *
     * @return array{name: string, pass: bool, detail: string}
     * @throws \Doctrine\ORM\NonUniqueResultException|\Doctrine\ORM\NoResultException
     */
    private function checkLciHwTypeCompleteness(): array
    {
        $count = $this->entityManager
            ->createQuery(
                'SELECT COUNT(sv.id) FROM App\Entity\SoftwareVersion sv
                WHERE sv.isLci = true AND sv.lciHwType IS NULL',
            )
            ->getSingleScalarResult();

        $pass = (int) $count === 0;

        return [
            'name' => 'All LCI records have a hardware type',
            'pass' => $pass,
            'detail' => $pass
                ? 'No LCI records with missing lciHwType'
                : (int) $count.' LCI record(s) have NULL lciHwType',
        ];
    }

    /**
     * Verifies sort_order values for specific anchor records.
     *
     * @return array{name: string, pass: bool, detail: string}
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function checkSortOrders(): array
    {
        $failures = [];

        foreach (self::EXPECTED_SORT_ORDERS as $expected) {
            $record = $this->entityManager
                ->createQuery(
                    'SELECT sv.sortOrder FROM App\Entity\SoftwareVersion sv
                    WHERE LOWER(sv.name) = LOWER(:name)
                    AND LOWER(sv.systemVersionAlt) = LOWER(:alt)',
                )
                ->setParameter('name', $expected['name'])
                ->setParameter('alt', $expected['versionAlt'])
                ->getOneOrNullResult();

            if ($record === null) {
                $failures[] = sprintf('"%s" / "%s" — not found', $expected['name'], $expected['versionAlt']);
                continue;
            }

            if ((int) $record['sortOrder'] !== $expected['sortOrder']) {
                $failures[] = sprintf(
                    '"%s" / "%s" — expected sort_order %d, got %d',
                    $expected['name'],
                    $expected['versionAlt'],
                    $expected['sortOrder'],
                    $record['sortOrder'],
                );
            }
        }

        $pass = count($failures) === 0;

        return [
            'name' => 'Sort order for anchor records',
            'pass' => $pass,
            'detail' => $pass
                ? 'All '.count(self::EXPECTED_SORT_ORDERS).' anchor records have correct sort_order'
                : implode('; ', $failures),
        ];
    }

    /**
     * Checks that the known latest system_version_alt values match the constants
     * hardcoded in FirmwareMatchService.
     *
     * @return array{name: string, pass: bool, detail: string}
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function checkKnownLatestVersions(): array
    {
        $knownLatest = [
            // Standard product lines — latest is v3.3.7.*
            ['name' => 'MMI Prime CIC', 'expectedAlt' => '3.3.7.mmipri.c'],
            ['name' => 'MMI Prime NBT', 'expectedAlt' => '3.3.7.mmipri.b'],
            ['name' => 'MMI Prime EVO', 'expectedAlt' => '3.3.7.mmipri.e'],
            ['name' => 'MMI PRO CIC', 'expectedAlt' => '3.3.7.mmipro.c'],
            ['name' => 'MMI PRO NBT', 'expectedAlt' => '3.3.7.mmipro.b'],
            ['name' => 'MMI PRO EVO', 'expectedAlt' => '3.3.7.mmipro.e'],
            // LCI product lines — latest is v3.4.4.*
            ['name' => 'LCI MMI Prime CIC', 'expectedAlt' => '3.4.4.mmiprilci'],
            ['name' => 'LCI MMI Prime NBT', 'expectedAlt' => '3.4.4.mmiprilci'],
            ['name' => 'LCI MMI Prime EVO', 'expectedAlt' => '3.4.4.mmiprilci'],
            ['name' => 'LCI MMI PRO CIC', 'expectedAlt' => '3.4.4.mmiprolci'],
            ['name' => 'LCI MMI PRO NBT', 'expectedAlt' => '3.4.4.mmiprolci'],
            ['name' => 'LCI MMI PRO EVO', 'expectedAlt' => '3.4.4.mmiprolci'],
        ];

        $failures = [];

        foreach ($knownLatest as $expected) {
            $record = $this->entityManager
                ->createQuery(
                    'SELECT sv.systemVersionAlt FROM App\Entity\SoftwareVersion sv
                    WHERE sv.name = :name AND sv.isLatest = true',
                )
                ->setParameter('name', $expected['name'])
                ->getOneOrNullResult();

            if ($record === null) {
                $failures[] = sprintf('"%s" — no "latest" entry found', $expected['name']);
                continue;
            }

            if (strcasecmp($record['systemVersionAlt'], $expected['expectedAlt']) !== 0) {
                $failures[] = sprintf(
                    '"%s" — expected latest "%s", got "%s"',
                    $expected['name'],
                    $expected['expectedAlt'],
                    $record['systemVersionAlt'],
                );
            }
        }

        $pass = count($failures) === 0;

        return [
            'name' => 'Latest version strings match expected values',
            'pass' => $pass,
            'detail' => $pass
                ? 'All 12 product line latest versions correct'
                : implode('; ', $failures),
        ];
    }

    /**
     * Spot-checks a selection of known download links from the original JSON.
     *
     * @return array{name: string, pass: bool, detail: string}
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function checkKnownDownloadLinks(): array
    {
        // Spot-check: first standard entry — MMI Prime CIC, version 3.1.1.2.mmi.c
        // JSON: st = 'https://drive.google.com/drive/folders/1wXfribAWWliCvUMQEtdDrnki3he9EKCK'
        $record = $this->entityManager
            ->createQuery(
                'SELECT sv.stLink FROM App\Entity\SoftwareVersion sv
                WHERE sv.name = :name AND sv.systemVersionAlt = :alt',
            )
            ->setParameter('name', 'MMI Prime CIC')
            ->setParameter('alt', '3.1.1.2.mmi.c')
            ->getOneOrNullResult();

        if ($record === null) {
            return [
                'name' => 'Known download link spot-check',
                'pass' => false,
                'detail' => 'MMI Prime CIC / 3.1.1.2.mmi.c — record not found',
            ];
        }

        $expectedSt = 'https://drive.google.com/drive/folders/1wXfribAWWliCvUMQEtdDrnki3he9EKCK';
        $pass = $record['stLink'] === $expectedSt;

        return [
            'name' => 'Known download link spot-check',
            'pass' => $pass,
            'detail' => $pass
                ? 'ST link for MMI Prime CIC / 3.1.1.2.mmi.c matches expected value'
                : sprintf('Expected "%s", got "%s"', $expectedSt, $record['stLink']),
        ];
    }

    // -------------------------------------------------------------------------
    // Output helpers
    // -------------------------------------------------------------------------

    /**
     * Renders a summary table of all check results.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output  The console output.
     * @param  array<int, array{name: string, pass: bool, detail: string}>  $checks  Check results.
     */
    private function renderSummaryTable(OutputInterface $output, array $checks): void
    {
        $table = new Table($output);
        $table->setHeaders(['Check', 'Result', 'Detail']);
        $table->setRows(array_map(
            static fn(array $c) => [
                $c['name'],
                $c['pass'] ? '<info>PASS</info>' : '<error>FAIL</error>',
                $c['detail'],
            ],
            $checks,
        ));
        $table->render();
    }
}
