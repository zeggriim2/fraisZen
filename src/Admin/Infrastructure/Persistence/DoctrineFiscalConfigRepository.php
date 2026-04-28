<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Persistence;

use App\Admin\Domain\Entity\FiscalConfig;
use App\Admin\Domain\Repository\FiscalConfigRepositoryInterface;
use Doctrine\ORM\EntityManagerInterface;

final class DoctrineFiscalConfigRepository implements FiscalConfigRepositoryInterface
{
    public function __construct(private readonly EntityManagerInterface $em) {}

    public function findByYear(int $year): ?FiscalConfig
    {
        return $this->em->find(FiscalConfig::class, $year);
    }

    public function findAll(): array
    {
        return $this->em->getRepository(FiscalConfig::class)
            ->findBy([], ['year' => 'DESC']);
    }

    public function save(FiscalConfig $config): void
    {
        $this->em->persist($config);
        $this->em->flush();
    }
}