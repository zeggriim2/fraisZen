<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Auth\Domain\Entity\User;
use App\Auth\Domain\ValueObject\UserId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    public const ADMIN_REF = 'user_admin';
    public const ALICE_REF = 'user_alice';
    public const BOB_REF = 'user_bob';
    public const CAROL_REF = 'user_carol';

    public function __construct(private readonly UserPasswordHasherInterface $hasher)
    {
    }

    public function load(ObjectManager $manager): void
    {
        $admin = new User(UserId::generate(), 'admin@fraisreel.fr', '');
        $admin->setPassword($this->hasher->hashPassword($admin, 'Admin1234!'));
        $admin->promoteToAdmin();
        $admin->setSubscriptionStatus('active');
        $admin->setDefaultYear(2025);
        $admin->setDefaultFiscalPower(5);
        $manager->persist($admin);
        $this->addReference(self::ADMIN_REF, $admin);

        $alice = new User(UserId::generate(), 'alice@example.com', '');
        $alice->setPassword($this->hasher->hashPassword($alice, 'Test1234!'));
        $alice->setSubscriptionStatus('active');
        $alice->setDefaultYear(2025);
        $alice->setDefaultFiscalPower(5);
        $manager->persist($alice);
        $this->addReference(self::ALICE_REF, $alice);

        $bob = new User(UserId::generate(), 'bob@example.com', '');
        $bob->setPassword($this->hasher->hashPassword($bob, 'Test1234!'));
        $bob->setSubscriptionStatus('active');
        $bob->setDefaultYear(2025);
        $bob->setDefaultFiscalPower(3);
        $manager->persist($bob);
        $this->addReference(self::BOB_REF, $bob);

        $carol = new User(UserId::generate(), 'carol@example.com', '');
        $carol->setPassword($this->hasher->hashPassword($carol, 'Test1234!'));
        $manager->persist($carol);
        $this->addReference(self::CAROL_REF, $carol);

        $manager->flush();
    }
}
