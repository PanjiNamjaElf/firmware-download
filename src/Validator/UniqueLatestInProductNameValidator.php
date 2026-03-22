<?php

declare(strict_types=1);

namespace App\Validator;

use App\Entity\SoftwareVersion;
use App\Repository\SoftwareVersionRepository;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;
use Symfony\Component\Validator\Exception\UnexpectedValueException;

/**
 * Validates that no other SoftwareVersion entry in the same product line
 * (same `name`) is already marked as `isLatest = true`.
 *
 * When editing an existing record, the current record itself is excluded from
 * the check to allow saving without changing the `isLatest` status.
 */
class UniqueLatestInProductNameValidator extends ConstraintValidator
{
    public function __construct(
        private readonly SoftwareVersionRepository $repository,
    ) {
        //
    }

    /**
     * Validates that the given SoftwareVersion does not conflict with an
     * existing "latest" entry for the same product line.
     *
     * @param  mixed  $value  The SoftwareVersion entity being validated.
     * @param  \Symfony\Component\Validator\Constraint  $constraint  The UniqueLatestInProductName constraint.
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (! $constraint instanceof UniqueLatestInProductName) {
            throw new UnexpectedTypeException($constraint, UniqueLatestInProductName::class);
        }

        if (! $value instanceof SoftwareVersion) {
            throw new UnexpectedValueException($value, SoftwareVersion::class);
        }

        // Only run the check when the current record is being marked as latest.
        if (! $value->isLatest()) {
            return;
        }

        // Skip if the product name is not yet set (incomplete form submission).
        if ($value->getName() === '') {
            return;
        }

        $conflicting = $this->repository->findConflictingLatest(
            productName: $value->getName(),
            excludeId: $value->getId(),
        );

        if ($conflicting === null) {
            return;
        }

        $this->context->buildViolation($constraint->message)
            ->setParameter('{{ name }}', $value->getName())
            ->setParameter('{{ existingVersion }}', $conflicting->getSystemVersion())
            ->atPath('isLatest')
            ->addViolation();
    }
}
