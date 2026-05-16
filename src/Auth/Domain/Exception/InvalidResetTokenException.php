<?php

declare(strict_types=1);

namespace App\Auth\Domain\Exception;

final class InvalidResetTokenException extends \DomainException
{
    public static function create(): self
    {
        return new self('Le lien de réinitialisation est invalide ou a expiré.');
    }
}
