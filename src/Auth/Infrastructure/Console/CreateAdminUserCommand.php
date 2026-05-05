<?php

declare(strict_types=1);

namespace App\Auth\Infrastructure\Console;

use App\Auth\Domain\Entity\User;
use App\Auth\Domain\Repository\UserRepositoryInterface;
use App\Auth\Domain\ValueObject\UserId;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un utilisateur admin avec un abonnement actif (dev/test uniquement)',
)]
final class CreateAdminUserCommand extends Command
{
    public function __construct(
        private readonly UserRepositoryInterface $userRepository,
        private readonly UserPasswordHasherInterface $hasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('email', null, InputOption::VALUE_REQUIRED, 'Adresse email')
            ->addOption('password', null, InputOption::VALUE_REQUIRED, 'Mot de passe');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $email = $input->getOption('email') ?? $io->ask('Email', 'admin@example.com');
        $password = $input->getOption('password') ?? $io->askHidden('Mot de passe');

        if (!$email || !$password) {
            $io->error('Email et mot de passe requis.');

            return Command::FAILURE;
        }

        if (null !== $this->userRepository->findByEmail($email)) {
            $io->warning("Un utilisateur avec l'email {$email} existe déjà.");

            return Command::FAILURE;
        }

        $user = new User(UserId::generate(), $email, '');
        $user->setPassword($this->hasher->hashPassword($user, $password));
        $user->setSubscriptionStatus('active');
        $user->promoteToAdmin();

        $this->userRepository->save($user);

        $io->success("Admin créé : {$email} (abonnement actif, ROLE_ADMIN)");

        return Command::SUCCESS;
    }
}