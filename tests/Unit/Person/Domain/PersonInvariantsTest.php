<?php

declare(strict_types=1);

use App\Person\Domain\Entity\Person;
use App\Person\Domain\ValueObject\PersonId;

$id = PersonId::fromString('00000000-0000-4000-8000-000000000002');

it('crée une personne avec prénom et nom valides', function () use ($id) {
    $p = new Person($id, 'user-1', 'Jean', 'Dupont');

    expect($p->firstName())->toBe('Jean')
        ->and($p->lastName())->toBe('Dupont');
});

it('rejette un prénom vide', function () use ($id) {
    expect(fn () => new Person($id, 'user-1', '', 'Dupont'))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejette un prénom composé uniquement d\'espaces', function () use ($id) {
    expect(fn () => new Person($id, 'user-1', '   ', 'Dupont'))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejette un nom vide', function () use ($id) {
    expect(fn () => new Person($id, 'user-1', 'Jean', ''))
        ->toThrow(\InvalidArgumentException::class);
});

it('rejette un prénom vide lors de update()', function () use ($id) {
    $p = new Person($id, 'user-1', 'Jean', 'Dupont');

    expect(fn () => $p->update('', 'Dupont', null))->toThrow(\InvalidArgumentException::class);
});

it('rejette un nom vide lors de update()', function () use ($id) {
    $p = new Person($id, 'user-1', 'Jean', 'Dupont');

    expect(fn () => $p->update('Jean', '', null))->toThrow(\InvalidArgumentException::class);
});