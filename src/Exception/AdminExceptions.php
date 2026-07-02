<?php

namespace App\Exception;

use Exception;

/**
 * Classe de base pour les exceptions administrateur
 */
class AdminExceptions extends Exception
{
}

/**
 * Exception levée quand une opération admin échoue.
 */
class AdminOperationException extends Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Exception levée quand une connexion DB ne peut pas être testée.
 */
class DatabaseConnectionException extends Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Exception levée quand une requête DB ne peut pas être exécutée.
 */
class DatabaseQueryException extends Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Exception levée quand une validation RBAC échoue.
 */
class RbacException extends Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}

/**
 * Exception levée quand un audit ne peut pas être enregistré.
 */
class AuditException extends Exception
{
    public function __construct(
        string $message,
        int $code = 0,
        ?Exception $previous = null,
    ) {
        parent::__construct($message, $code, $previous);
    }
}
