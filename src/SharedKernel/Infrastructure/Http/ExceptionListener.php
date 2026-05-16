<?php

declare(strict_types=1);

namespace App\SharedKernel\Infrastructure\Http;

use App\SharedKernel\Domain\Exception\ConflictException;
use App\SharedKernel\Domain\Exception\NotFoundException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\KernelEvents;

#[AsEventListener(event: KernelEvents::EXCEPTION)]
final class ExceptionListener
{
    /** @var array<class-string<\Throwable>, int> */
    private const EXCEPTION_HTTP_MAP = [
        ConflictException::class => Response::HTTP_CONFLICT,
        NotFoundException::class => Response::HTTP_NOT_FOUND,
        \DomainException::class => Response::HTTP_UNPROCESSABLE_ENTITY,
    ];

    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();

        foreach (self::EXCEPTION_HTTP_MAP as $class => $status) {
            if ($exception instanceof $class) {
                $event->setResponse(new JsonResponse(['error' => $exception->getMessage()], $status));

                return;
            }
        }
    }
}
