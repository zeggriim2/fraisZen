<?php

declare(strict_types=1);

use App\Auth\Domain\Exception\InvalidResetTokenException;
use App\Auth\Domain\Exception\UserAlreadyExistsException;
use App\Auth\Domain\Exception\UserNotFoundException;
use App\Expense\Domain\Exception\BaremeKilometriqueNotFoundException;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\Person\Domain\Exception\FavoriteRouteNotFoundException;
use App\Person\Domain\Exception\PersonNotFoundException;
use App\SharedKernel\Domain\Exception\ConflictException;
use App\SharedKernel\Domain\Exception\NotFoundException;

it('NotFoundException extends DomainException', function () {
    $e = new class('msg') extends NotFoundException {};
    expect($e)->toBeInstanceOf(\DomainException::class);
});

it('ConflictException extends DomainException', function () {
    $e = new class('msg') extends ConflictException {};
    expect($e)->toBeInstanceOf(\DomainException::class);
});

it('UserNotFoundException est une NotFoundException', function () {
    expect(UserNotFoundException::withId('abc'))->toBeInstanceOf(NotFoundException::class);
});

it('PersonNotFoundException est une NotFoundException', function () {
    expect(PersonNotFoundException::withId('abc'))->toBeInstanceOf(NotFoundException::class);
});

it('ExpenseNotFoundException est une NotFoundException', function () {
    expect(ExpenseNotFoundException::withId('abc'))->toBeInstanceOf(NotFoundException::class);
});

it('FavoriteRouteNotFoundException est une NotFoundException', function () {
    expect(FavoriteRouteNotFoundException::withId('abc'))->toBeInstanceOf(NotFoundException::class);
});

it('BaremeKilometriqueNotFoundException est une NotFoundException', function () {
    expect(BaremeKilometriqueNotFoundException::forYear(2024))->toBeInstanceOf(NotFoundException::class);
});

it('UserAlreadyExistsException est une ConflictException', function () {
    expect(UserAlreadyExistsException::withEmail('a@b.com'))->toBeInstanceOf(ConflictException::class);
});

it('InvalidResetTokenException est une DomainException générique', function () {
    expect(InvalidResetTokenException::create())->toBeInstanceOf(\DomainException::class);
    expect(InvalidResetTokenException::create())->not->toBeInstanceOf(NotFoundException::class);
    expect(InvalidResetTokenException::create())->not->toBeInstanceOf(ConflictException::class);
});
