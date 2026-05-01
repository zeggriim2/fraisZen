<?php

declare(strict_types=1);

namespace App\Person\Domain\Entity;

use App\Person\Domain\ValueObject\FavoriteRouteId;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'person_favorite_route')]
class FavoriteRoute
{
    #[ORM\Id]
    #[ORM\Column(type: 'string', length: 36)]
    private string $id;

    #[ORM\Column(type: 'string', length: 36)]
    private string $personId;

    #[ORM\Column(type: 'string', length: 100)]
    private string $name;

    #[ORM\Column(type: 'string', length: 255)]
    private string $departure;

    #[ORM\Column(type: 'string', length: 255)]
    private string $arrival;

    #[ORM\Column(type: 'string', length: 20)]
    private string $vehicleType;

    #[ORM\Column(type: 'integer', nullable: true)]
    private ?int $vehiclePower;

    #[ORM\Column(type: 'boolean')]
    private bool $isElectric;

    #[ORM\Column(type: 'boolean')]
    private bool $roundTrip;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        FavoriteRouteId $id,
        string $personId,
        string $name,
        string $departure,
        string $arrival,
        string $vehicleType,
        ?int $vehiclePower,
        bool $isElectric,
        bool $roundTrip,
    ) {
        $this->id = $id->value();
        $this->personId = $personId;
        $this->name = $name;
        $this->departure = $departure;
        $this->arrival = $arrival;
        $this->vehicleType = $vehicleType;
        $this->vehiclePower = $vehiclePower;
        $this->isElectric = $isElectric;
        $this->roundTrip = $roundTrip;
        $this->createdAt = new \DateTimeImmutable();
    }

    public function id(): FavoriteRouteId
    {
        return FavoriteRouteId::fromString($this->id);
    }

    public function personId(): string
    {
        return $this->personId;
    }

    public function update(
        string $name,
        string $departure,
        string $arrival,
        string $vehicleType,
        ?int $vehiclePower,
        bool $isElectric,
        bool $roundTrip,
    ): void {
        $this->name = $name;
        $this->departure = $departure;
        $this->arrival = $arrival;
        $this->vehicleType = $vehicleType;
        $this->vehiclePower = $vehiclePower;
        $this->isElectric = $isElectric;
        $this->roundTrip = $roundTrip;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'personId' => $this->personId,
            'name' => $this->name,
            'departure' => $this->departure,
            'arrival' => $this->arrival,
            'vehicleType' => $this->vehicleType,
            'vehiclePower' => $this->vehiclePower,
            'isElectric' => $this->isElectric,
            'roundTrip' => $this->roundTrip,
            'createdAt' => $this->createdAt->format('Y-m-d H:i:s'),
        ];
    }
}
