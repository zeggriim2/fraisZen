<?php

declare(strict_types=1);

namespace App\DataFixtures;

use App\Expense\Domain\Entity\MealExpense;
use App\Expense\Domain\Entity\ParkingExpense;
use App\Expense\Domain\Entity\RemoteWorkExpense;
use App\Expense\Domain\Entity\TollExpense;
use App\Expense\Domain\Entity\TravelExpense;
use App\Expense\Domain\Enum\VehicleType;
use App\Expense\Domain\ValueObject\ExpenseId;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Persistence\ObjectManager;

/**
 * Dépenses de développement sur 2024 & 2025.
 *
 * Alice Dupont (5 CV, voiture, Paris 13e → La Défense 18 km A/R)
 *   - 2025 : jan–déc avec congés en août ; ~100 trajets, télétravail, repas, péages, parkings
 *   - 2024 : oct–déc pour tester la navigation multi-année
 *
 * Marc Dupont (3 CV, voiture, Lyon 3e → Villeurbanne 8 km A/R) — jan–mars 2025
 *
 * Robert Martin / Bob (3 CV voiture + 5 CV moto, Bordeaux → Mérignac 15 km A/R) — jan–mars 2025
 */
class ExpenseFixtures extends Fixture implements DependentFixtureInterface
{
    public function getDependencies(): array
    {
        return [PersonFixtures::class];
    }

    public function load(ObjectManager $manager): void
    {
        /** @var \App\Person\Domain\Entity\Person $alicePerson */
        $alicePerson = $this->getReference(PersonFixtures::ALICE_PERSON_REF, \App\Person\Domain\Entity\Person::class);
        /** @var \App\Person\Domain\Entity\Person $marcPerson */
        $marcPerson = $this->getReference(PersonFixtures::MARC_PERSON_REF, \App\Person\Domain\Entity\Person::class);
        /** @var \App\Person\Domain\Entity\Person $bobPerson */
        $bobPerson = $this->getReference(PersonFixtures::BOB_PERSON_REF, \App\Person\Domain\Entity\Person::class);

        $this->loadAliceExpenses2025($manager, $alicePerson->id()->value());
        $this->loadAliceExpenses2024($manager, $alicePerson->id()->value());
        $this->loadMarcExpenses2025($manager, $marcPerson->id()->value());
        $this->loadBobExpenses2025($manager, $bobPerson->id()->value());
    }

    // ─── Helpers ──────────────────────────────────────────────────────────────

    private function d(string $date): \DateTimeImmutable
    {
        return new \DateTimeImmutable($date);
    }

    private function travel(
        string $personId,
        string $date,
        string $departure,
        string $arrival,
        float $km,
        int $cv,
        bool $roundTrip = true,
        VehicleType $vehicleType = VehicleType::Car,
        bool $electric = false,
        ?string $description = null,
    ): TravelExpense {
        return new TravelExpense(
            ExpenseId::generate(),
            $personId,
            $this->d($date),
            $description,
            $departure,
            $arrival,
            $km,
            $cv,
            $roundTrip,
            $vehicleType,
            $electric,
        );
    }

    private function remoteWork(string $personId, string $date, float $allowance = 2.80): RemoteWorkExpense
    {
        return new RemoteWorkExpense(ExpenseId::generate(), $personId, $this->d($date), null, $allowance);
    }

    private function toll(string $personId, string $date, float $amount, string $dep, string $arr): TollExpense
    {
        return new TollExpense(ExpenseId::generate(), $personId, $this->d($date), null, $amount, $dep, $arr);
    }

    private function meal(
        string $personId,
        string $date,
        float $mealAmount,
        float $homeMeal = 5.40,
        float $ticketContrib = 0.0,
        bool $withoutReceipt = false,
        ?string $description = null,
    ): MealExpense {
        return new MealExpense(
            ExpenseId::generate(),
            $personId,
            $this->d($date),
            $description,
            $mealAmount,
            $homeMeal,
            $ticketContrib,
            $withoutReceipt,
        );
    }

    private function parking(string $personId, string $date, float $amount, ?string $location = null): ParkingExpense
    {
        return new ParkingExpense(ExpenseId::generate(), $personId, $this->d($date), null, $amount, $location);
    }

    // ─── Alice — 2025 ─────────────────────────────────────────────────────────

    private function loadAliceExpenses2025(ObjectManager $manager, string $personId): void
    {
        $bureau = ['Paris 13e', 'La Défense', 18.0, 5];

        // Janvier — 10 jours bureau, 5 télétravail, 1 péage déplacement pro, 1 repas
        foreach (['2025-01-06', '2025-01-07', '2025-01-08', '2025-01-09', '2025-01-10',
            '2025-01-13', '2025-01-14', '2025-01-15', '2025-01-16', '2025-01-17'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2025-01-20', '2025-01-21', '2025-01-22', '2025-01-23', '2025-01-24'] as $d) {
            $manager->persist($this->remoteWork($personId, $d));
        }
        $manager->persist($this->toll($personId, '2025-01-10', 28.40, 'Paris', 'Lyon'));
        $manager->persist($this->meal($personId, '2025-01-15', 18.50, 5.40, 4.00));
        $manager->flush();

        // Février — 9 jours bureau, 3 télétravail, 1 parking
        foreach (['2025-02-03', '2025-02-04', '2025-02-05', '2025-02-06', '2025-02-07',
            '2025-02-10', '2025-02-11', '2025-02-12', '2025-02-13'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2025-02-17', '2025-02-18', '2025-02-19'] as $d) {
            $manager->persist($this->remoteWork($personId, $d));
        }
        $manager->persist($this->parking($personId, '2025-02-20', 15.00, 'Parking Gare de Lyon'));
        $manager->flush();

        // Mars — 10 jours bureau, 2 télétravail, 1 péage, 1 repas sans reçu
        foreach (['2025-03-03', '2025-03-04', '2025-03-05', '2025-03-06', '2025-03-07',
            '2025-03-10', '2025-03-11', '2025-03-12', '2025-03-13', '2025-03-14'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2025-03-17', '2025-03-18'] as $d) {
            $manager->persist($this->remoteWork($personId, $d));
        }
        $manager->persist($this->toll($personId, '2025-03-12', 6.70, 'Paris', 'Versailles'));
        $manager->persist($this->meal($personId, '2025-03-20', 0.0, 5.40, 0.0, true, 'Déjeuner sans reçu'));
        $manager->flush();

        // Avril — 9 jours bureau, 3 télétravail, 1 repas client
        foreach (['2025-04-01', '2025-04-02', '2025-04-03', '2025-04-04',
            '2025-04-07', '2025-04-08', '2025-04-09', '2025-04-10', '2025-04-11'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2025-04-14', '2025-04-15', '2025-04-16'] as $d) {
            $manager->persist($this->remoteWork($personId, $d));
        }
        $manager->persist($this->meal($personId, '2025-04-10', 22.80, 5.40, 0.0, false, 'Déjeuner client'));
        $manager->flush();

        // Mai — 10 jours bureau, 5 télétravail, 1 parking
        foreach (['2025-05-05', '2025-05-06', '2025-05-07', '2025-05-08', '2025-05-09',
            '2025-05-12', '2025-05-13', '2025-05-14', '2025-05-15', '2025-05-16'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2025-05-19', '2025-05-20', '2025-05-21', '2025-05-22', '2025-05-23'] as $d) {
            $manager->persist($this->remoteWork($personId, $d));
        }
        $manager->persist($this->parking($personId, '2025-05-15', 8.00, 'Parking Beaugrenelle'));
        $manager->flush();

        // Juin — 10 jours bureau, 2 télétravail, 1 péage
        foreach (['2025-06-02', '2025-06-03', '2025-06-04', '2025-06-05', '2025-06-06',
            '2025-06-09', '2025-06-10', '2025-06-11', '2025-06-12', '2025-06-13'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2025-06-16', '2025-06-17'] as $d) {
            $manager->persist($this->remoteWork($personId, $d));
        }
        $manager->persist($this->toll($personId, '2025-06-05', 12.30, 'Paris', 'Chartres'));
        $manager->flush();

        // Juillet — présence réduite avant les congés
        foreach (['2025-07-01', '2025-07-02', '2025-07-03', '2025-07-04'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2025-07-07', '2025-07-08'] as $d) {
            $manager->persist($this->remoteWork($personId, $d));
        }
        $manager->flush();

        // Août — vacances, aucune dépense

        // Septembre — reprise, 10 jours bureau, 3 télétravail, 2 repas
        foreach (['2025-09-01', '2025-09-02', '2025-09-03', '2025-09-04', '2025-09-05',
            '2025-09-08', '2025-09-09', '2025-09-10', '2025-09-11', '2025-09-12'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2025-09-15', '2025-09-16', '2025-09-17'] as $d) {
            $manager->persist($this->remoteWork($personId, $d));
        }
        $manager->persist($this->meal($personId, '2025-09-09', 24.00, 5.40, 4.00, false, 'Déjeuner prospect'));
        $manager->persist($this->meal($personId, '2025-09-25', 0.0, 5.40, 0.0, true));
        $manager->flush();

        // Octobre — 10 jours bureau, 4 télétravail, 1 péage, 1 parking aéroport
        foreach (['2025-10-01', '2025-10-02', '2025-10-03',
            '2025-10-06', '2025-10-07', '2025-10-08', '2025-10-09', '2025-10-10',
            '2025-10-13', '2025-10-14'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2025-10-15', '2025-10-16', '2025-10-17', '2025-10-20'] as $d) {
            $manager->persist($this->remoteWork($personId, $d));
        }
        $manager->persist($this->toll($personId, '2025-10-08', 9.50, 'Paris', 'Orly'));
        $manager->persist($this->parking($personId, '2025-10-08', 22.00, 'Parking Orly Sud'));
        $manager->flush();

        // Novembre — 9 jours bureau, 5 télétravail, 1 repas
        foreach (['2025-11-03', '2025-11-04', '2025-11-05', '2025-11-06', '2025-11-07',
            '2025-11-10', '2025-11-12', '2025-11-13', '2025-11-14'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2025-11-17', '2025-11-18', '2025-11-19', '2025-11-20', '2025-11-21'] as $d) {
            $manager->persist($this->remoteWork($personId, $d));
        }
        $manager->persist($this->meal($personId, '2025-11-06', 19.90, 5.40, 4.00));
        $manager->flush();

        // Décembre — 7 jours bureau, 3 télétravail, 1 trajet véhicule électrique
        foreach (['2025-12-01', '2025-12-02', '2025-12-03', '2025-12-04', '2025-12-05',
            '2025-12-08', '2025-12-09'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2025-12-15', '2025-12-16', '2025-12-17'] as $d) {
            $manager->persist($this->remoteWork($personId, $d));
        }
        $manager->persist($this->travel(
            $personId,
            '2025-12-10',
            'Paris 13e',
            'Versailles',
            24.0,
            5,
            false,
            VehicleType::Car,
            true,
            'Déplacement véhicule électrique de fonction',
        ));
        $manager->flush();
    }

    // ─── Alice — 2024 ─────────────────────────────────────────────────────────

    private function loadAliceExpenses2024(ObjectManager $manager, string $personId): void
    {
        $bureau = ['Paris 13e', 'La Défense', 18.0, 5];

        // Octobre 2024 — 8 jours bureau, 3 télétravail (allowance 2024 = 2.70)
        foreach (['2024-10-01', '2024-10-02', '2024-10-03', '2024-10-04',
            '2024-10-07', '2024-10-08', '2024-10-09', '2024-10-10'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2024-10-14', '2024-10-15', '2024-10-16'] as $d) {
            $manager->persist($this->remoteWork($personId, $d, 2.70));
        }
        $manager->flush();

        // Novembre 2024 — 9 jours bureau, 4 télétravail, 1 péage
        foreach (['2024-11-04', '2024-11-05', '2024-11-06', '2024-11-07',
            '2024-11-12', '2024-11-13', '2024-11-14', '2024-11-18', '2024-11-19'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2024-11-20', '2024-11-21', '2024-11-22', '2024-11-25'] as $d) {
            $manager->persist($this->remoteWork($personId, $d, 2.70));
        }
        $manager->persist($this->toll($personId, '2024-11-07', 14.20, 'Paris', 'Reims'));
        $manager->flush();

        // Décembre 2024 — 6 jours bureau, 2 télétravail, 1 repas équipe
        foreach (['2024-12-02', '2024-12-03', '2024-12-04', '2024-12-05', '2024-12-06',
            '2024-12-09'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2024-12-16', '2024-12-17'] as $d) {
            $manager->persist($this->remoteWork($personId, $d, 2.70));
        }
        $manager->persist($this->meal($personId, '2024-12-05', 31.00, 5.35, 4.00, false, 'Repas équipe'));
        $manager->flush();
    }

    // ─── Marc — 2025 ──────────────────────────────────────────────────────────

    private function loadMarcExpenses2025(ObjectManager $manager, string $personId): void
    {
        $bureau = ['Lyon 3e', 'Villeurbanne', 8.0, 3];

        // Janvier
        foreach (['2025-01-06', '2025-01-07', '2025-01-08'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        $manager->persist($this->remoteWork($personId, '2025-01-13'));
        $manager->flush();

        // Février
        foreach (['2025-02-03', '2025-02-04', '2025-02-05', '2025-02-06'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        $manager->persist($this->remoteWork($personId, '2025-02-10'));
        $manager->persist($this->meal($personId, '2025-02-04', 14.50, 5.40, 3.00));
        $manager->flush();

        // Mars
        foreach (['2025-03-03', '2025-03-04', '2025-03-05', '2025-03-06', '2025-03-07'] as $d) {
            $manager->persist($this->travel($personId, $d, ...$bureau));
        }
        foreach (['2025-03-10', '2025-03-11'] as $d) {
            $manager->persist($this->remoteWork($personId, $d));
        }
        $manager->flush();
    }

    // ─── Bob — 2025 ───────────────────────────────────────────────────────────

    private function loadBobExpenses2025(ObjectManager $manager, string $personId): void
    {
        // Janvier — voiture 3 CV
        foreach (['2025-01-06', '2025-01-07', '2025-01-08', '2025-01-09', '2025-01-10'] as $d) {
            $manager->persist($this->travel($personId, $d, 'Bordeaux Centre', 'Mérignac', 15.0, 3));
        }
        foreach (['2025-01-13', '2025-01-14'] as $d) {
            $manager->persist($this->remoteWork($personId, $d));
        }
        $manager->flush();

        // Février — voiture 3 CV
        foreach (['2025-02-03', '2025-02-04', '2025-02-05', '2025-02-06', '2025-02-07'] as $d) {
            $manager->persist($this->travel($personId, $d, 'Bordeaux Centre', 'Mérignac', 15.0, 3));
        }
        $manager->persist($this->meal($personId, '2025-02-05', 16.80, 5.40, 0.0));
        $manager->flush();

        // Mars — moto 5 CV (teste un type de véhicule différent)
        foreach (['2025-03-03', '2025-03-04', '2025-03-05', '2025-03-06', '2025-03-07'] as $d) {
            $manager->persist($this->travel(
                $personId,
                $d,
                'Bordeaux Centre',
                'Mérignac',
                15.0,
                5,
                true,
                VehicleType::Motorcycle,
            ));
        }
        foreach (['2025-03-10', '2025-03-11', '2025-03-12'] as $d) {
            $manager->persist($this->remoteWork($personId, $d));
        }
        $manager->persist($this->toll($personId, '2025-03-05', 4.80, 'Bordeaux', 'Mérignac'));
        $manager->flush();
    }
}
