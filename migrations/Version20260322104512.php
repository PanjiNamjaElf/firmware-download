<?php

declare(strict_types = 1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Initial schema migration.
 *
 * Creates two tables:
 *   - `admin_user`       — admin panel authentication accounts
 *   - `software_version` — firmware version records (seeded from JSON)
 *
 * Written using the Doctrine DBAL Schema API (not raw SQL) so it is
 * portable across SQLite.
 */
final class Version20260322104512 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Initial schema: admin_user and software_version tables.';
    }

    /**
     * Creates both tables with all indexes.
     *
     * @param  \Doctrine\DBAL\Schema\Schema  $schema  The target schema.
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function up(Schema $schema): void
    {
        // -----------------------------------------------------------------
        // admin_user
        // -----------------------------------------------------------------

        $adminUser = $schema->createTable('admin_user');

        $adminUser->addColumn('id', 'integer', [
            'autoincrement' => true,
            'notnull' => true,
        ]);

        $adminUser->addColumn('username', 'string', [
            'length' => 180,
            'notnull' => true,
        ]);

        $adminUser->addColumn('roles', 'json', [
            'notnull' => true,
        ]);

        $adminUser->addColumn('password', 'string', [
            'length' => 255,
            'notnull' => true,
        ]);

        $adminUser->setPrimaryKey(['id']);
        $adminUser->addUniqueIndex(['username'], 'uniq_admin_user_username');

        // -----------------------------------------------------------------
        // software_version
        // -----------------------------------------------------------------

        $sv = $schema->createTable('software_version');

        $sv->addColumn('id', 'integer', [
            'autoincrement' => true,
            'notnull' => true,
        ]);

        $sv->addColumn('name', 'string', [
            'length' => 100,
            'notnull' => true,
        ]);

        // Full version string with "v" prefix — display only.
        $sv->addColumn('system_version', 'string', [
            'length' => 100,
            'notnull' => true,
        ]);

        // Version string WITHOUT "v" prefix — used for case-insensitive matching.
        $sv->addColumn('system_version_alt', 'string', [
            'length' => 100,
            'notnull' => true,
        ]);

        // General/legacy download folder link (maybe empty).
        $sv->addColumn('link', 'text', [
            'notnull' => false,
            'default' => null,
        ]);

        // ST download link.
        $sv->addColumn('st_link', 'text', [
            'notnull' => false,
            'default' => null,
        ]);

        // GD download link.
        $sv->addColumn('gd_link', 'text', [
            'notnull' => false,
            'default' => null,
        ]);

        // true = customer is already on the latest version; no download link shown.
        $sv->addColumn('is_latest', 'boolean', [
            'notnull' => true,
            'default' => false,
        ]);

        // true = this entry belongs to the LCI (Life Cycle Impulse) product family.
        $sv->addColumn('is_lci', 'boolean', [
            'notnull' => true,
            'default' => false,
        ]);

        // For LCI entries: 'CIC', 'NBT', or 'EVO'. NULL for standard entries.
        $sv->addColumn('lci_hw_type', 'string', [
            'length' => 10,
            'notnull' => false,
            'default' => null,
        ]);

        // Controls iteration order; mirrors original JSON array index.
        $sv->addColumn('sort_order', 'integer', [
            'notnull' => true,
            'default' => 0,
        ]);

        $sv->addColumn('created_at', 'datetime_immutable', [
            'notnull' => true,
        ]);

        $sv->addColumn('updated_at', 'datetime_immutable', [
            'notnull' => true,
        ]);

        $sv->setPrimaryKey(['id']);
        $sv->addIndex(['is_lci'], 'idx_sw_is_lci');
        $sv->addIndex(['sort_order', 'name'], 'idx_sw_sort');
    }

    /**
     * Drops both tables.
     *
     * @param  \Doctrine\DBAL\Schema\Schema  $schema  The target schema.
     * @throws \Doctrine\DBAL\Schema\SchemaException
     */
    public function down(Schema $schema): void
    {
        $schema->dropTable('software_version');
        $schema->dropTable('admin_user');
    }
}
