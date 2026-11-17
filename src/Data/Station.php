<?php

declare(strict_types=1);

namespace Dyrynda\SigenCloud\Data;

use Saloon\Http\Response;

final readonly class Station
{
    /**
     * @param  list<string>  $acSnList  AC charger serial numbers.
     * @param  list<string>  $dcSnList  DC charger serial numbers.
     */
    public function __construct(
        public int $id,
        public bool $onGrid,
        public float $pvCapacity,
        public float $batteryCapacity,
        public int $status,
        public string $statusDesc,
        public ?string $shutdownReason,
        public array $acSnList,
        public array $dcSnList,
    ) {}

    public static function fromResponse(Response $response): self
    {
        /** @var array{stationId: int, onGrid: bool, pvCapacity: float|int, batteryCapacity: float|int, status: int, statusDesc: string, shutdownReason?: ?string, acSnList?: ?list<int|string>, dcSnList?: ?list<int|string>} $station */
        $station = $response->json('data');

        return new self(
            id: $station['stationId'],
            onGrid: $station['onGrid'],
            pvCapacity: (float) $station['pvCapacity'],
            batteryCapacity: (float) $station['batteryCapacity'],
            status: $station['status'],
            statusDesc: $station['statusDesc'],
            shutdownReason: $station['shutdownReason'] ?? null,
            acSnList: array_map(strval(...), $station['acSnList'] ?? []),
            dcSnList: array_map(strval(...), $station['dcSnList'] ?? []),
        );
    }
}
