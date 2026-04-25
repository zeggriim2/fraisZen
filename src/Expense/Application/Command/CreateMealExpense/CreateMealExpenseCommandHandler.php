<?php

declare(strict_types=1);

namespace App\Expense\Application\Command\CreateMealExpense;

use App\Expense\Domain\Entity\MealExpense;
use App\Expense\Domain\Repository\ExpenseRepositoryInterface;
use App\Expense\Domain\ValueObject\ExpenseId;
use App\SharedKernel\Application\Bus\CommandHandlerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler(bus: 'command.bus')]
final readonly class CreateMealExpenseCommandHandler implements CommandHandlerInterface
{
    public function __construct(private ExpenseRepositoryInterface $repository) {}

    public function __invoke(CreateMealExpenseCommand $command): string
    {
        $id = ExpenseId::generate();
        $this->repository->save(new MealExpense(
            id: $id,
            personId: $command->personId,
            date: new \DateTimeImmutable($command->date),
            description: $command->description,
            mealAmount: $command->mealAmount,
        ));
        return $id->value();
    }
}
