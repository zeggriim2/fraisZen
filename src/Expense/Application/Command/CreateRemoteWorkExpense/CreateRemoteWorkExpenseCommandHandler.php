<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\CreateRemoteWorkExpense;

use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use App\Expense\Domain\Entity\RemoteWorkExpense;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateRemoteWorkExpenseCommandHandler implements CommandHandlerInterface
{
    public function __construct(
        private ExpenseRepositoryInterface $repository,
        private FiscalConfigRepositoryInterface $fiscalConfigRepository,
    ) {}

    public function __invoke(CreateRemoteWorkExpenseCommand $command): string
    {
        $year   = (int) (new \DateTimeImmutable($command->date))->format('Y');
        $config = $this->fiscalConfigRepository->findByYear($year);
        $rate   = $config?->remoteWorkDailyAllowance() ?? $command->dailyAllowance;

        $id = ExpenseId::generate();
        $this->repository->save(new RemoteWorkExpense(
            id: $id,
            personId: $command->personId,
            date: new \DateTimeImmutable($command->date),
            description: $command->description,
            dailyAllowance: $rate,
        ));
        return $id->value();
    }
}
