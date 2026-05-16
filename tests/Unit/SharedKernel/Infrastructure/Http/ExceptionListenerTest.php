<?php

declare(strict_types=1);

use App\Auth\Domain\Exception\InvalidResetTokenException;
use App\Auth\Domain\Exception\UserAlreadyExistsException;
use App\Auth\Domain\Exception\UserNotFoundException;
use App\Expense\Domain\Exception\ExpenseNotFoundException;
use App\SharedKernel\Domain\Exception\ConflictException;
use App\SharedKernel\Domain\Exception\NotFoundException;
use App\SharedKernel\Infrastructure\Http\ExceptionListener;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

function makeExceptionEvent(\Throwable $exception): ExceptionEvent
{
    $kernel = new class implements HttpKernelInterface {
        public function handle(Request $request, int $type = self::MAIN_REQUEST, bool $catch = true): Response
        {
            return new Response();
        }
    };

    return new ExceptionEvent($kernel, Request::create('/api/test'), HttpKernelInterface::MAIN_REQUEST, $exception);
}

it('mappe une NotFoundException en 404', function () {
    $event = makeExceptionEvent(new class('Resource not found') extends NotFoundException {});

    (new ExceptionListener())($event);

    expect($event->getResponse()?->getStatusCode())->toBe(404);
    expect(json_decode($event->getResponse()->getContent(), true))->toBe(['error' => 'Resource not found']);
});

it('mappe une ConflictException en 409', function () {
    $event = makeExceptionEvent(new class('Already exists') extends ConflictException {});

    (new ExceptionListener())($event);

    expect($event->getResponse()?->getStatusCode())->toBe(409);
    expect(json_decode($event->getResponse()->getContent(), true))->toBe(['error' => 'Already exists']);
});

it('mappe une DomainException générique en 422', function () {
    $event = makeExceptionEvent(new \DomainException('Règle métier violée'));

    (new ExceptionListener())($event);

    expect($event->getResponse()?->getStatusCode())->toBe(422);
    expect(json_decode($event->getResponse()->getContent(), true))->toBe(['error' => 'Règle métier violée']);
});

it('mappe InvalidResetTokenException en 422', function () {
    $event = makeExceptionEvent(InvalidResetTokenException::create());

    (new ExceptionListener())($event);

    expect($event->getResponse()?->getStatusCode())->toBe(422);
});

it('mappe UserNotFoundException en 404', function () {
    $event = makeExceptionEvent(UserNotFoundException::withId('abc-123'));

    (new ExceptionListener())($event);

    expect($event->getResponse()?->getStatusCode())->toBe(404);
});

it('mappe UserAlreadyExistsException en 409', function () {
    $event = makeExceptionEvent(UserAlreadyExistsException::withEmail('a@b.com'));

    (new ExceptionListener())($event);

    expect($event->getResponse()?->getStatusCode())->toBe(409);
});

it('mappe ExpenseNotFoundException en 404', function () {
    $event = makeExceptionEvent(ExpenseNotFoundException::withId('abc-123'));

    (new ExceptionListener())($event);

    expect($event->getResponse()?->getStatusCode())->toBe(404);
});

it('ne touche pas aux exceptions non-domaine', function () {
    $event = makeExceptionEvent(new \RuntimeException('Infra error'));

    (new ExceptionListener())($event);

    expect($event->getResponse())->toBeNull();
});

it('ne touche pas aux exceptions PHP natives', function () {
    $event = makeExceptionEvent(new \InvalidArgumentException('Bad input'));

    (new ExceptionListener())($event);

    expect($event->getResponse())->toBeNull();
});

it('le message d\'erreur du body correspond au message de l\'exception', function () {
    $message = 'Expense with id "xyz" not found.';
    $event = makeExceptionEvent(ExpenseNotFoundException::withId('xyz'));

    (new ExceptionListener())($event);

    $body = json_decode($event->getResponse()->getContent(), true);
    expect($body['error'])->toBe($message);
});
