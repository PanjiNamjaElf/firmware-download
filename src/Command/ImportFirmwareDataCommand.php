<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\SoftwareVersion;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Imports firmware version data from the bundled JSON file into the database.
 *
 * Source: data/softwareversions.json (the original legacy file, shipped with the project).
 *
 * Run once after database creation to seed all records so the app
 * immediately behaves identically to the current live system.
 *
 * Usage:
 *   php bin/console app:import-firmware-data                       # dry-run preview
 *   php bin/console app:import-firmware-data --execute             # actually import
 *   php bin/console app:import-firmware-data --execute --force     # wipe + reimport
 *
 * By default, the command runs in dry-run mode and prints a summary table
 * without touching the database. Pass --execute to commit the changes.
 *
 * --force truncates the software_version table before importing, making the
 * command fully idempotent. Without --force, existing records (matched by
 * name + system_version_alt) are skipped, and only new rows are inserted.
 *
 * Derivation rules applied during import
 * ----------------------------------------
 * is_lci      → true when the `name` field starts with "LCI" (case-sensitive).
 *               Mirrors the legacy check: strpos($row['name'], 'LCI') === 0
 *
 * lci_hw_type → 'CIC', 'NBT', or 'EVO' when is_lci = true,
 *               derived by searching for those strings in the `name` field.
 *               Mirrors the legacy check: stripos($row['name'], $lciHwType)
 *               NULL for all standard (non-LCI) entries.
 *
 * sort_order  → the zero-based array index from the JSON file.
 *               This preserves the original iteration order, which matters
 *               because the matching loop breaks on first match.
 *
 * link fields → empty strings in JSON are stored as NULL in the database.
 *               The service layer coalesces NULL back to '' when building
 *               the API response, so the output is identical to the legacy system.
 */
#[AsCommand(
    name: 'app:import-firmware-data',
    description: 'Imports firmware versions from the bundled JSON file into the database.',
)]
class ImportFirmwareDataCommand extends Command
{
    /**
     * Path to the source JSON file, relative to the project root.
     */
    private const DATA_FILE = '/data/softwareversions.json';

    /**
     * LCI hardware type strings to search for in the product name.
     * Evaluated in order — first match wins.
     */
    private const LCI_HW_TYPES = ['CIC', 'NBT', 'EVO'];

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    /**
     * Defines --execute and --force options.
     */
    protected function configure(): void
    {
        $this
            ->addOption(
                name: 'execute',
                mode: InputOption::VALUE_NONE,
                description: 'Actually write records to the database. Omitting this flag runs a dry-run preview.',
            )
            ->addOption(
                name: 'force',
                mode: InputOption::VALUE_NONE,
                description: 'Truncate the software_version table before importing. Makes the command fully idempotent.',
            )
            ->setHelp(
                <<<'HELP'
                The <info>app:import-firmware-data</info> command imports all firmware version
                records from the bundled JSON file into the database.

                <comment>Dry-run (default — no changes to the database):</comment>
                  <info>php bin/console app:import-firmware-data</info>

                <comment>Import new records only (skips existing name+version combinations):</comment>
                  <info>php bin/console app:import-firmware-data --execute</info>

                <comment>Wipe and reimport everything (fully idempotent):</comment>
                  <info>php bin/console app:import-firmware-data --execute --force</info>

                HELP,
            );
    }

    /**
     * Executes the import.
     *
     * @param  \Symfony\Component\Console\Input\InputInterface  $input  The command input.
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output  The command output.
     * @return int Command exit code.
     * @throws \JsonException|\Doctrine\DBAL\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $execute = (bool) $input->getOption('execute');
        $force = (bool) $input->getOption('force');

        $io->title('Firmware Data Import');

        if (! $execute) {
            $io->note('DRY-RUN mode — no changes will be written. Pass --execute to import.');
        }

        // -----------------------------------------------------------------
        // Load and validate the source JSON file.
        // -----------------------------------------------------------------

        $filePath = $this->projectDir.self::DATA_FILE;

        if (! file_exists($filePath)) {
            $io->error(sprintf(
                'Source file not found: %s'.PHP_EOL.
                'Make sure the file exists at data/softwareversions.json in the project root.',
                $filePath,
            ));

            return Command::FAILURE;
        }

        $json = file_get_contents($filePath);

        if ($json === false) {
            $io->error('Could not read the source JSON file.');

            return Command::FAILURE;
        }

        $rows = json_decode($json, true, 512, JSON_THROW_ON_ERROR);

        if (! is_array($rows)) {
            $io->error('The JSON file did not decode to an array.');

            return Command::FAILURE;
        }

        $io->text(sprintf('Source file: <info>%s</info>', realpath($filePath)));
        $io->text(sprintf('Records in JSON: <info>%d</info>', count($rows)));
        $io->newLine();

        // -----------------------------------------------------------------
        // Optionally truncate the table before import.
        // -----------------------------------------------------------------

        if ($force && $execute) {
            $connection = $this->entityManager->getConnection();

            // SQLite does not support TRUNCATE — use DELETE FROM instead.
            $connection->executeStatement('DELETE FROM software_version');

            $io->warning('Table software_version has been cleared (--force was passed).');
        }

        // -----------------------------------------------------------------
        // Build a lookup of existing records to detect duplicates.
        // Key format: "name||system_version_alt" (lowercase, trimmed).
        // -----------------------------------------------------------------

        $existing = $this->buildExistingLookup();

        // -----------------------------------------------------------------
        // Process each row from the JSON.
        // -----------------------------------------------------------------

        $counters = [
            'inserted' => 0,
            'skipped' => 0,
            'lci' => 0,
            'standard' => 0,
            'latest' => 0,
            'duplicateJson' => 0,
        ];

        $previewRows = [];
        $seenThisRun = []; // Tracks duplicates within the JSON itself.

        foreach ($rows as $index => $row) {
            $result = $this->processRow(
                row: $row,
                sortOrder: $index,
                existing: $existing,
                seenThisRun: $seenThisRun,
                execute: $execute,
                counters: $counters,
            );

            $previewRows[] = $result;
        }

        // -----------------------------------------------------------------
        // Flush all persisted entities in one transaction.
        // -----------------------------------------------------------------

        if ($execute) {
            $this->entityManager->flush();
        }

        // -----------------------------------------------------------------
        // Output a summary table.
        // -----------------------------------------------------------------

        $this->renderPreviewTable($output, $previewRows);

        $io->newLine();
        $io->definitionList(
            ['Records in JSON' => count($rows)],
            ['Inserted' => $counters['inserted']],
            ['Skipped (existing)' => $counters['skipped']],
            ['Duplicate in JSON' => $counters['duplicateJson'].' (inserted; see note below)'],
            ['Standard entries' => $counters['standard']],
            ['LCI entries' => $counters['lci']],
            ['"Latest" flags' => $counters['latest'].' (expected: 12)'],
        );

        if ($counters['duplicateJson'] > 0) {
            $io->note(
                'The source JSON contains '.$counters['duplicateJson'].' duplicate row(s) '
                .'(same name + system_version_alt). Both rows were imported to preserve '
                .'the exact 116-row array. The legacy matching loop breaks on first match, '
                .'so the second row is unreachable during API lookups. '
                .'This is a known quirk of the original data.',
            );
        }

        if ($counters['latest'] !== 12) {
            $io->warning(sprintf(
                '"Latest" flag count is %d, expected 12 (one per product line). '
                .'Review the source JSON or the admin panel.',
                $counters['latest'],
            ));
        } else {
            $io->success(sprintf(
                '%d records processed. '
                .'All 12 product lines have exactly one "latest" entry.',
                $execute ? $counters['inserted'] : count($rows),
            ));
        }

        if (! $execute) {
            $io->note('This was a dry-run. Run with --execute to write to the database.');
        }

        return Command::SUCCESS;
    }

    /**
     * Processes a single JSON row: derives all fields, checks for duplicates,
     * and either persists a new SoftwareVersion entity or records a skip.
     *
     * @param  array<string, mixed>  $row  The raw JSON row.
     * @param  int  $sortOrder  The zero-based array index (iteration order).
     * @param  array<string, bool>  $existing  Lookup of already-persisted records.
     * @param  array<string, bool>  $seenThisRun  Tracks duplicates within the current JSON.
     * @param  bool  $execute  Whether to actually persist the entity.
     * @param  array<string, int>  $counters  Running totals (mutated by reference).
     * @return array{status: string, name: string, version: string, lci: string, hwType: string, latest: string}
     */
    private function processRow(
        array $row,
        int $sortOrder,
        array $existing,
        array &$seenThisRun,
        bool $execute,
        array &$counters,
    ): array {
        $name = trim((string) ($row['name'] ?? ''));
        $systemVersion = trim((string) ($row['system_version'] ?? ''));
        $versionAlt = trim((string) ($row['system_version_alt'] ?? ''));
        $link = $this->nullIfEmpty($row['link'] ?? '');
        $stLink = $this->nullIfEmpty($row['st'] ?? '');
        $gdLink = $this->nullIfEmpty($row['gd'] ?? '');
        $isLatest = (bool) ($row['latest'] ?? false);

        // Derive is_lci and lci_hw_type from the name string.
        $isLci = str_starts_with($name, 'LCI');
        $lciHwType = $isLci ? $this->deriveLciHwType($name) : null;

        // Duplicate detection key (case-insensitive).
        $lookupKey = strtolower($name.'||'.$versionAlt);

        // Duplicate detection within the source JSON.
        //
        // The legacy JSON contains one known duplicate: "MMI PRO NBT" / "3.3.4.mmipro.b"
        // appears at both index 80 and 81 with identical field values.
        //
        // Both rows are imported intentionally — the database mirrors the source JSON
        // exactly (116 rows). The legacy matching loop broke on the first match, so
        // index 81 was already unreachable in the original system. This behaviour is
        // preserved: findAllOrdered() returns rows in sort_order ASC (= JSON index),
        // and FirmwareMatchService breaks on first match, making index 81 unreachable here too.
        $isDuplicateInJson = isset($seenThisRun[$lookupKey]);
        $seenThisRun[$lookupKey] = true;

        if ($isDuplicateInJson) {
            ++$counters['duplicateJson'];
        }

        // Track totals.
        if ($isLci) {
            ++$counters['lci'];
        } else {
            ++$counters['standard'];
        }

        if ($isLatest) {
            ++$counters['latest'];
        }

        // Skip if a matching record already exists in the DB (unless --force was used,
        // which clears the table beforehand making $existing always empty).
        if (isset($existing[$lookupKey])) {
            ++$counters['skipped'];

            return [
                'status' => 'SKIP',
                'name' => $name,
                'version' => $systemVersion,
                'lci' => $isLci ? 'Yes' : 'No',
                'hwType' => $lciHwType ?? '—',
                'latest' => $isLatest ? '✓' : '',
            ];
        }

        // Persist the entity.
        if ($execute) {
            $entity = new SoftwareVersion();
            $entity->setName($name);
            $entity->setSystemVersion($systemVersion);
            $entity->setSystemVersionAlt($versionAlt);
            $entity->setLink($link);
            $entity->setStLink($stLink);
            $entity->setGdLink($gdLink);
            $entity->setIsLatest($isLatest);
            $entity->setIsLci($isLci);
            $entity->setLciHwType($lciHwType);
            $entity->setSortOrder($sortOrder);

            $this->entityManager->persist($entity);
        }

        ++$counters['inserted'];

        return [
            'status' => $execute ? 'INSERT' : 'WOULD INSERT',
            'name' => $name,
            'version' => $systemVersion,
            'lci' => $isLci ? 'Yes' : 'No',
            'hwType' => $lciHwType ?? '—',
            'latest' => $isLatest ? '✓' : '',
        ];
    }

    /**
     * Builds a set of existing (name, system_version_alt) pairs already in the database.
     * Used to skip records that were previously imported.
     *
     * @return array<string, bool> Keys are lowercase "name||system_version_alt" strings.
     */
    private function buildExistingLookup(): array
    {
        $results = $this->entityManager
            ->createQuery('SELECT sv.name, sv.systemVersionAlt FROM App\Entity\SoftwareVersion sv')
            ->getArrayResult();

        $lookup = [];

        foreach ($results as $row) {
            $key = strtolower($row['name'].'||'.$row['systemVersionAlt']);
            $lookup[$key] = true;
        }

        return $lookup;
    }

    /**
     * Derives the LCI hardware type from the product name string.
     *
     * Searches for 'CIC', 'NBT', 'EVO' in the name and returns the first match.
     * Mirrors the legacy runtime derivation: stripos($row['name'], $lciHwType)
     *
     * @param  string  $name  The product line name (e.g. "LCI MMI Prime CIC").
     * @return string|null The hardware type string, or null if none found.
     */
    private function deriveLciHwType(string $name): ?string
    {
        foreach (self::LCI_HW_TYPES as $hwType) {
            if (stripos($name, $hwType) !== false) {
                return $hwType;
            }
        }

        return null;
    }

    /**
     * Converts an empty string to null for storage.
     * Null values serialize as empty strings in the API response via ?? ''.
     *
     * @param  mixed  $value  The raw JSON field value.
     * @return string|null
     */
    private function nullIfEmpty(mixed $value): ?string
    {
        $str = trim((string) $value);

        return $str !== '' ? $str : null;
    }

    /**
     * Renders the per-row preview table to the console output.
     *
     * Limits to 20 rows in non-verbose mode so the terminal is not flooded.
     * Pass -v (verbose) to see all rows.
     *
     * @param  \Symfony\Component\Console\Output\OutputInterface  $output  Console output.
     * @param  array<int, array<string, string>>  $previewRows  Processed row data.
     */
    private function renderPreviewTable(OutputInterface $output, array $previewRows): void
    {
        $isVerbose = $output->isVerbose();
        $rows = $isVerbose ? $previewRows : array_slice($previewRows, 0, 20);

        $table = new Table($output);
        $table->setHeaders(['Status', 'Product Name', 'Version', 'LCI?', 'HW Type', 'Latest?']);
        $table->setRows(array_map(
            static fn(array $r) => [
                $r['status'],
                $r['name'],
                $r['version'],
                $r['lci'],
                $r['hwType'],
                $r['latest'],
            ],
            $rows,
        ));
        $table->render();

        if (! $isVerbose && count($previewRows) > 20) {
            $output->writeln(sprintf(
                '<comment>... and %d more rows. Run with -v to see all.</comment>',
                count($previewRows) - 20,
            ));
        }
    }
}
