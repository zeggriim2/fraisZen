<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Person\Domain\Entity\FavoriteRoute;
use App\Person\Domain\Entity\Person;
use App\Person\Domain\ValueObject\FavoriteRouteId;
use App\Person\Domain\ValueObject\PersonId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

class PersonFixtures extends Fixture implements DependentFixtureInterface
{
    public const ALICE_PERSON_REF = 'person_alice';
    public const MARC_PERSON_REF = 'person_marc';
    public const BOB_PERSON_REF = 'person_bob';

    public function getDependencies(): array
    {
        return [UserFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var \App\Auth\Domain\Entity\User $alice */
        $alice = $this->getReference(UserFixtures::ALICE_REF, \App\Auth\Domain\Entity\User::class);
        /** @var \App\Auth\Domain\Entity\User $bob */
        $bob = $this->getReference(UserFixtures::BOB_REF, \App\Auth\Domain\Entity\User::class);

        // ─── Persons ───────────────────────────────────────────────────────────

        $alicePerson = new Person(
            PersonId::generate(),
            $alice->id()->value(),
            'Alice',
            'Dupont',
            'alice@example.com',
            true,
        );
        $manager->persist($alicePerson);
        $this->addReference(self::ALICE_PERSON_REF, $alicePerson);

        $marcPerson = new Person(
            PersonId::generate(),
            $alice->id()->value(),
            'Marc',
            'Dupont',
            'marc.dupont@example.com',
            false,
        );
        $manager->persist($marcPerson);
        $this->addReference(self::MARC_PERSON_REF, $marcPerson);

        $bobPerson = new Person(
            PersonId::generate(),
            $bob->id()->value(),
            'Robert',
            'Martin',
            'bob@example.com',
            true,
        );
        $manager->persist($bobPerson);
        $this->addReference(self::BOB_PERSON_REF, $bobPerson);

        // ─── Routes favorites ──────────────────────────────────────────────────

        $manager->persist(new FavoriteRoute(
            FavoriteRouteId::generate(),
            $alicePerson->id()->value(),
            'Domicile → Bureau',
            'Paris 13e',
            'La Défense',
            'car',
            5,
            false,
            true,
        ));

        $manager->persist(new FavoriteRoute(
            FavoriteRouteId::generate(),
            $alicePerson->id()->value(),
            'Client Principal',
            'Paris 13e',
            'Neuilly-sur-Seine',
            'car',
            5,
            false,
            false,
        ));

        $manager->persist(new FavoriteRoute(
            FavoriteRouteId::generate(),
            $marcPerson->id()->value(),
            'Domicile → Travail',
            'Lyon 3e',
            'Villeurbanne',
            'car',
            3,
            false,
            true,
        ));

        $manager->persist(new FavoriteRoute(
            FavoriteRouteId::generate(),
            $bobPerson->id()->value(),
            'Domicile → Agence',
            'Bordeaux Centre',
            'Mérignac',
            'car',
            3,
            false,
            true,
        ));

        $manager->flush();
    }
}
