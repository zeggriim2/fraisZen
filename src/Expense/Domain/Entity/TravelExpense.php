<?php

declare(strict_types=1);

namespace App\Expense\Domain\Entity;

use App\Expense\Domain\Enum\ExpenseType;
use App\Expense\Domain\Enum\VehicleType;
use App\Expense\Domain\ValueObject\ExpenseId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class TravelExpense extends Expense
{
    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $departure;

    #[ORM\Column(type: 'string', length: 255, nullable: true)]
    private ?string $arrival;

    #[ORM\Column(type: 'decimal', precision: 8, scale: 2)]
    private string $distanceKm;

    #[ORM\Column(type: 'smallint', nullable: true)]
    private ?int $vehiclePower;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $roundTrip;

    #[ORM\Column(type: 'string', length: 20, enumType: VehicleType::class, options: ['default' => 'car'])]
    private VehicleType $vehicleType;

    #[ORM\Column(type: 'boolean', options: ['default' => false])]
    private bool $isElectric;

    public function __construct(
        ExpenseId $id,
        string $personId,
        \DateTimeImmutable $date,
        ?string $description,
        ?string $departure,
        ?string $arrival,
        float $distanceKm,
        ?int $vehiclePower,
        bool $roundTrip = false,
        VehicleType $vehicleType = VehicleType::Car,
        bool $isElectric = false,
    ) {
        parent::__construct($id, $personId, $date, $description);
        $this->departure = $departure;
        $this->arrival = $arrival;
        $this->distanceKm = (string) $distanceKm;
        $this->vehiclePower = $vehiclePower;
        $this->roundTrip = $roundTrip;
        $this->vehicleType = $vehicleType;
        $this->isElectric = $isElectric;
    }

    public function setDeparture(?string $departure): void
    {
        $this->departure = $departure;
        $this->touch();
    }

    public function setArrival(?string $arrival): void
    {
        $this->arrival = $arrival;
        $this->touch();
    }

    public function setDistanceKm(float $km): void
    {
        $this->distanceKm = (string) $km;
        $this->touch();
    }

    public function setVehiclePower(?int $power): void
    {
        $this->vehiclePower = $power;
        $this->touch();
    }

    public function setRoundTrip(bool $rt): void
    {
        $this->roundTrip = $rt;
        $this->touch();
    }

    public function setVehicleType(VehicleType $vt): void
    {
        $this->vehicleType = $vt;
        $this->touch();
    }

    public function setIsElectric(bool $e): void
    {
        $this->isElectric = $e;
        $this->touch();
    }

    public function type(): ExpenseType
    {
        return ExpenseType::Travel;
    }

    public function amount(): float
    {
        return 0.0;
    }

    public function departure(): ?string
    {
        return $this->departure;
    }

    public function arrival(): ?string
    {
        return $this->arrival;
    }

    public function distanceKm(): float
    {
        return (float) $this->distanceKm;
    }

    public function vehiclePower(): ?int
    {
        return $this->vehiclePower;
    }

    public function roundTrip(): bool
    {
        return $this->roundTrip;
    }

    public function vehicleType(): VehicleType
    {
        return $this->vehicleType;
    }

    public function isElectric(): bool
    {
        return $this->isElectric;
    }

    public function effectiveDistanceKm(): float
    {
        return $this->roundTrip ? $this->distanceKm() * 2 : $this->distanceKm();
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_merge($this->baseArray(), [
            'departure' => $this->departure,
            'arrival' => $this->arrival,
            'distanceKm' => $this->distanceKm(),
            'vehiclePower' => $this->vehiclePower,
            'roundTrip' => $this->roundTrip,
            'vehicleType' => $this->vehicleType->value,
            'isElectric' => $this->isElectric,
        ]);
    }
}
