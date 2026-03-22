# Firmware Download

Symfony 6.4 app that reimplements the firmware download page at
`bimmer-tech.net/carplay/software-download`. Customers enter their software
and hardware version to get the correct download link. An admin panel lets the
team manage firmware records without touching code.

## Requirements

- PHP 8.2+ with extensions: `pdo_sqlite`, `mbstring`, `xml`, `openssl`
- Composer 2.x

## Setup

```bash
git clone https://github.com/PanjiNamjaElf/firmware-download.git firmware-download
cd firmware-download

cp .env.local .env
composer install

mkdir -p var/data

php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate --no-interaction
php bin/console app:import-firmware-data --execute
php bin/console app:create-admin-user
```

Start the server:

```bash
php -S 127.0.0.1:8000 -t public/
```

Open `http://127.0.0.1:8000`.

## Running with Docker

If you'd rather not set up PHP locally:

```bash
docker compose up --build
```

This handles everything automatically — dependencies, database, seeding, and the
default admin account (`admin` / `secret1234`). Open `http://localhost:8000`.

To set your own credentials:

```bash
ADMIN_USERNAME=yourname ADMIN_PASSWORD=yourpassword docker compose up --build
```

## Running Tests

```bash
php vendor/bin/phpunit
```

The test suite covers the core firmware matching logic in `FirmwareMatchService`
(35 tests, 91 assertions). No database is required — the service tests use
in-memory fixtures derived from `data/softwareversions.json`.

## Pages

| URL | Description |
|---|---|
| `/carplay/software-download` | Customer firmware lookup |
| `/admin` | Admin panel (login required) |
| `/api/carplay/software/version` | Firmware check API (POST) |

## Managing Software Versions

Log in at `/admin/login` with the account created during setup.

The dashboard shows a health indicator — there should always be exactly
**12 "latest" entries**, one per product line. A warning appears if that
count is off.

**To release a new firmware version:**

1. Find the current latest entry for that product line, uncheck **Is Latest**, save.
2. Create a new entry with the updated version and download links, check **Is Latest**, save.
3. Check the dashboard still shows 12 latest entries.

**A few things to keep in mind:**

- Product name must match the existing entries for that product line exactly.
- Version must start with `v` (e.g. `v3.3.7`).
- LCI entries require a hardware type — CIC, NBT, or EVO.
- Only one entry per product line can be marked as Latest; the form will reject duplicates.
