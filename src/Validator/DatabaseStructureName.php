<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * Validateur pour les noms de structure de base de données.
 * 
 * Empêche les injections SQL et les caractères invalides.
 */
#[Attribute]
class DatabaseStructureNameValidator extends ConstraintValidator
{
    public function validate($value, Constraint $constraint): void
    {
        if (null === $value || '' === $value) {
            return;
        }

        if (!is_string($value)) {
            throw new UnexpectedTypeException($value, 'string');
        }

        // Regex: alphanumerique, underscore, tiret, point
        // Format: schema.table ou juste table
        if (!preg_match('/^[a-zA-Z0-9_\-\.]+$/', $value)) {
            $this->context->buildViolation('Structure name contains invalid characters')
                ->addViolation();
            return;
        }

        // Vérifier la longueur
        if (strlen($value) > 255) {
            $this->context->buildViolation('Structure name is too long (max 255 characters)')
                ->addViolation();
        }
    }
}

/**
 * Constraint pour la validation des noms de structure.
 */
class DatabaseStructureName extends Constraint
{
    public string $message = 'Invalid database structure name';

    public function validatedBy(): string
    {
        return DatabaseStructureNameValidator::class;
    }
}
