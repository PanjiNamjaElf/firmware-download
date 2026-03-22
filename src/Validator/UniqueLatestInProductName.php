<?php

declare(strict_types=1);

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Constraint: enforces that only one SoftwareVersion entry per product line
 * (grouped by the `name` field) can be marked as `isLatest = true`.
 *
 * Applied at the class level on SoftwareVersion so it has access to both
 * the `name` and `isLatest` fields together.
 *
 * Without this constraint, an admin could accidentally check "Is Latest" on
 * two entries for the same product line (e.g. two "MMI Prime CIC" entries),
 * which would cause the first match in the iteration to win — making the
 * second "latest" record unreachable and silently incorrect.
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
class UniqueLatestInProductName extends Constraint
{
    public string $message = 'The product line "{{ name }}" already has another entry marked as the latest version '
    .'({{ existingVersion }}). Only one entry per product line can be marked as latest. '
    .'Please uncheck "Is Latest" on the other entry first.';

    /**
     * Declares this as a class-level constraint so the validator receives
     * the full SoftwareVersion object, not a single field value.
     *
     * @return string
     */
    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
